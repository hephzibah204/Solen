<?php
require_once dirname(__DIR__) . '/config.php';

function get_db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dir = dirname(DB_PATH);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // ── POWER UP: Performance Optimizations ──────────────────────────────
    $pdo->exec('PRAGMA journal_mode=WAL;');      // High concurrency
    $pdo->exec('PRAGMA synchronous=NORMAL;');   // Speed + Safety balance
    $pdo->exec('PRAGMA foreign_keys=ON;');      // Data integrity
    $pdo->exec('PRAGMA cache_size=-16000;');    // 16MB cache
    $pdo->exec('PRAGMA mmap_size=268435456;');  // 256MB memory mapping for speed
    $pdo->exec('PRAGMA auto_vacuum=INCREMENTAL;');// Prevent fragmentation
    $pdo->exec('PRAGMA temp_store=MEMORY;');     // Faster temporary tables
    
    init_schema($pdo);
    run_migrations($pdo);
    return $pdo;
}

/**
 * Perform database maintenance: VACUUM, ANALYZE, and integrity check.
 * Should be called via cron periodically.
 */
function db_optimize(): array {
    $db = get_db();
    $start = microtime(true);
    $db->exec('VACUUM;');
    $db->exec('ANALYZE;');
    $check = $db->query('PRAGMA integrity_check;')->fetchColumn();
    $time = round(microtime(true) - $start, 3);
    return ['ok' => ($check === 'ok'), 'time' => $time, 'check' => $check];
}

/**
 * Create a compressed backup of the database.
 */
function db_backup(string $targetPath): bool {
    $dbPath = DB_PATH;
    if (!file_exists($dbPath)) return false;
    // Use SQLite's backup API if available, or just copy the file
    // For simplicity and safety in a PWA context, we copy + gzip
    $backup = $targetPath . '/solen_backup_' . date('Ymd_His') . '.sqlite';
    if (!copy($dbPath, $backup)) return false;
    
    if (function_exists('gzencode')) {
        file_put_contents($backup . '.gz', gzencode(file_get_contents($backup), 9));
        unlink($backup);
    }
    return true;
}

function init_schema(PDO $pdo): void {
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        name        TEXT NOT NULL,
        email       TEXT UNIQUE NOT NULL,
        password    TEXT NOT NULL,
        role        TEXT NOT NULL DEFAULT 'user',
        plan        TEXT NOT NULL DEFAULT 'free',
        trial_ends  TEXT,
        created_at  TEXT DEFAULT (datetime('now')),
        last_login  TEXT
    );

    CREATE TABLE IF NOT EXISTS subscriptions (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        plan            TEXT NOT NULL,
        status          TEXT NOT NULL DEFAULT 'active',
        amount          REAL DEFAULT 0,
        billing_cycle   TEXT DEFAULT 'monthly',
        started_at      TEXT DEFAULT (datetime('now')),
        expires_at      TEXT,
        cancelled_at    TEXT,
        notes           TEXT
    );

    CREATE TABLE IF NOT EXISTS blog_posts (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        title           TEXT NOT NULL,
        slug            TEXT UNIQUE NOT NULL,
        excerpt         TEXT,
        content         TEXT,
        meta_title      TEXT,
        meta_desc       TEXT,
        featured_image  TEXT,
        status          TEXT DEFAULT 'draft',
        author_id       INTEGER REFERENCES users(id),
        category        TEXT DEFAULT 'General',
        tags            TEXT,
        views           INTEGER DEFAULT 0,
        published_at    TEXT,
        created_at      TEXT DEFAULT (datetime('now')),
        updated_at      TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS coach_profiles (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id     INTEGER UNIQUE NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        purpose     TEXT,
        tone        TEXT,
        challenge   TEXT,
        coach_name  TEXT,
        day_streak  INTEGER DEFAULT 0,
        last_date   TEXT,
        program_day INTEGER DEFAULT 0,
        updated_at  TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS mood_logs (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        score       INTEGER NOT NULL,
        label       TEXT,
        emoji       TEXT,
        logged_date TEXT DEFAULT (date('now')),
        created_at  TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS coach_memory (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        summary     TEXT,
        themes      TEXT,
        session_date TEXT DEFAULT (date('now')),
        created_at  TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS settings (
        key   TEXT PRIMARY KEY,
        value TEXT
    );

    CREATE TABLE IF NOT EXISTS user_sessions (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        token      TEXT UNIQUE NOT NULL,
        expires_at TEXT NOT NULL,
        created_at TEXT DEFAULT (datetime('now'))
    );

    INSERT OR IGNORE INTO settings (key, value) VALUES
        ('site_name',        'Solen'),
        ('site_tagline',     'The AI Wellness Coach That Remembers You'),
        ('trial_days',       '7'),
        ('maintenance_mode', '0'),
        ('smtp_host',        ''),
        ('smtp_port',        '587'),
        ('smtp_user',        ''),
        ('smtp_pass',        ''),
        ('from_email',       'hello@getsolen.com'),
        ('stripe_pk',        ''),
        ('stripe_sk',        ''),
        ('google_analytics', ''),
        ('footer_text',      '© 2026 Solen Inc. All rights reserved.');
    ");

    // Seed admin user if none exists — generate a random password, never a hardcoded one
    $admin = $pdo->query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetch();
    if (!$admin) {
        $tempPass = bin2hex(random_bytes(12)); // 24-char random hex, unique per deployment
        $hash     = password_hash($tempPass, PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (name, email, password, role, plan) VALUES (?, ?, ?, 'admin', 'premium')")
            ->execute(['Admin', 'admin@getsolen.com', $hash]);

        // Write credentials to a root-readable file; delete after first login
        $credFile = dirname(DB_PATH) . '/.admin_credentials';
        file_put_contents(
            $credFile,
            "Solen Admin Credentials — DELETE THIS FILE AFTER FIRST LOGIN\n" .
            "Email:    admin@getsolen.com\n" .
            "Password: {$tempPass}\n" .
            "Generated: " . date('Y-m-d H:i:s') . " UTC\n"
        );
        @chmod($credFile, 0600);
    }
}

// ── QUERY HELPERS ──────────────────────────────────────────────────────────
function db_query(string $sql, array $params = []): array {
    $st = get_db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function db_one(string $sql, array $params = []): ?array {
    $st = get_db()->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    return $row ?: null;
}

function db_run(string $sql, array $params = []): int {
    $db = get_db();
    $st = $db->prepare($sql);
    $st->execute($params);
    return (int)$db->lastInsertId();
}

function db_count(string $table, string $where = '1', array $params = []): int {
    $row = db_one("SELECT COUNT(*) as n FROM $table WHERE $where", $params);
    return (int)($row['n'] ?? 0);
}

function get_setting(string $key, string $default = ''): string {
    // Priority 1: Environment Variables (e.g. from .env)
    $envKey = strtoupper($key);
    $val = getenv($envKey);
    if ($val !== false) return (string)$val;

    // Priority 2: Database Settings
    $row = db_one("SELECT value FROM settings WHERE key=?", [$key]);
    return $row ? $row['value'] : $default;
}

function set_setting(string $key, string $value): void {
    db_run("INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value=excluded.value", [$key, $value]);
}

// NOTE: appended by upgrade — payment_transactions table + new settings
// (init_schema already runs on first connection; new tables added via migration below)
function run_migrations(PDO $pdo): void {
    // Add notification_prefs to coach_profiles if not present
    $cpCols = array_column($pdo->query("PRAGMA table_info(coach_profiles)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('notification_prefs', $cpCols)) {
        $pdo->exec("ALTER TABLE coach_profiles ADD COLUMN notification_prefs TEXT DEFAULT '{}'");
    }
    if (!in_array('program_completed_at', $cpCols)) {
        $pdo->exec("ALTER TABLE coach_profiles ADD COLUMN program_completed_at TEXT");
    }
    if (!in_array('relationship_level', $cpCols)) {
        $pdo->exec("ALTER TABLE coach_profiles ADD COLUMN relationship_level INTEGER DEFAULT 1");
    }
    if (!in_array('personality_style', $cpCols)) {
        $pdo->exec("ALTER TABLE coach_profiles ADD COLUMN personality_style TEXT DEFAULT 'gentle'");
    }
    if (!in_array('growth_stage', $cpCols)) {
        $pdo->exec("ALTER TABLE coach_profiles ADD COLUMN growth_stage TEXT DEFAULT 'exploration'");
    }

    // Add notes column to mood_logs if not present
    $moodCols = array_column($pdo->query("PRAGMA table_info(mood_logs)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('notes', $moodCols)) {
        $pdo->exec("ALTER TABLE mood_logs ADD COLUMN notes TEXT DEFAULT ''");
    }

    // Add stripe_customer_id to users if it doesn't exist yet
    $cols = array_column($pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('stripe_customer_id', $cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN stripe_customer_id TEXT");
    }
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_stripe_cid ON users(stripe_customer_id)");

    // Per-user AI rate limiting counters (one row per user per UTC day)
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS ai_rate_limits (
        user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        limit_date   TEXT NOT NULL,          -- UTC date YYYY-MM-DD
        request_count INTEGER DEFAULT 0,
        PRIMARY KEY (user_id, limit_date)
    );
    ");

    $pdo->exec("
    CREATE TABLE IF NOT EXISTS password_resets (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        token_hash TEXT    NOT NULL,
        expires_at TEXT    NOT NULL,
        used_at    TEXT,
        created_at TEXT DEFAULT (datetime('now'))
    );
    CREATE INDEX IF NOT EXISTS idx_password_resets_token ON password_resets(token_hash);
    ");

    $pdo->exec("
    CREATE TABLE IF NOT EXISTS payment_transactions (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        gateway     TEXT NOT NULL,
        tx_ref      TEXT,
        plan        TEXT NOT NULL,
        billing     TEXT DEFAULT 'monthly',
        amount      REAL DEFAULT 0,
        status      TEXT DEFAULT 'pending',
        created_at  TEXT DEFAULT (datetime('now'))
    );
    ");

    $pdo->exec("
    CREATE TABLE IF NOT EXISTS chat_sessions (
        id           INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        session_date TEXT NOT NULL DEFAULT (date('now')),
        messages     TEXT NOT NULL DEFAULT '[]',
        message_count INTEGER DEFAULT 0,
        created_at   TEXT DEFAULT (datetime('now')),
        updated_at   TEXT DEFAULT (datetime('now')),
        UNIQUE(user_id, session_date)
    );
    CREATE INDEX IF NOT EXISTS idx_chat_sessions_user ON chat_sessions(user_id, session_date);
    ");

    // ── EMOTIONAL INTELLIGENCE TABLES (Phase 2) ───────────────────────────
    // Emotional state history — one row per detected emotion event
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS emotion_events (
        id           INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        state        TEXT NOT NULL,           -- 'crisis'|'high_distress'|'burnout'|'anxiety_high'|'low'|'neutral'|'positive'
        score        REAL DEFAULT 0,          -- 0.0 (positive) → 1.0 (crisis)
        indicators   TEXT DEFAULT '[]',       -- JSON array of matched keywords
        source       TEXT DEFAULT 'chat',     -- 'chat'|'mood_log'|'manual'
        created_at   TEXT DEFAULT (datetime('now'))
    );
    CREATE INDEX IF NOT EXISTS idx_emotion_events_user ON emotion_events(user_id, created_at);
    ");

    // Audit log — admin visibility into security-relevant actions (Phase 1 Security)
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS audit_log (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id    INTEGER REFERENCES users(id) ON DELETE SET NULL,
        action     TEXT NOT NULL,
        detail     TEXT,
        ip         TEXT,
        created_at TEXT DEFAULT (datetime('now'))
    );
    CREATE INDEX IF NOT EXISTS idx_audit_log_user ON audit_log(user_id, created_at);
    CREATE INDEX IF NOT EXISTS idx_audit_log_action ON audit_log(action, created_at);
    ");

    // Emotional nudge log — track which nudges have been shown (avoid repeats)
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS nudge_log (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        nudge_hash TEXT NOT NULL,
        shown_at   TEXT DEFAULT (datetime('now'))
    );
    CREATE INDEX IF NOT EXISTS idx_nudge_log_user ON nudge_log(user_id);
    ");

    // ── SEED NEW SETTINGS KEYS ────────────────────────────────────────────
    $new = [
        ['from_name',             'Solen'],
        ['cron_secret',           ''],
        ['claude_model',         'claude-sonnet-4-20250514'],
        ['gemini_api_key',       ''],
        ['gemini_model',         'gemini-1.5-flash'],
        ['huggingface_api_key',  ''],
        ['huggingface_model',    'mistralai/Mistral-7B-Instruct-v0.3'],
        ['openrouter_api_key',   ''],
        ['openrouter_model',     'meta-llama/llama-3.3-70b-instruct'],
        ['groq_api_key',         ''],
        ['groq_model',           'llama-3.3-70b-versatile'],
        ['hypereal_api_key',     ''],
        ['hypereal_model',       'gpt-5.5'],
        ['fireworks_api_key',    ''],
        ['fireworks_model',      'accounts/fireworks/models/minimax-m2p77'],
        ['payment_gateway',      'stripe'],
        ['flutterwave_pk',       ''],
        ['flutterwave_sk',       ''],
        ['flutterwave_encryption',''],
        ['flutterwave_webhook_secret',''],
        ['paystack_pk',          ''],
        ['paystack_sk',          ''],
        ['meta_title_home',      'Solen — AI Wellness Coach That Remembers You'],
        ['meta_desc_home',       'Solen is your personal AI wellness coach. Get daily check-ins, mood tracking, and personalized support. Start free — no credit card required.'],
        ['og_image',             ''],
        ['twitter_handle',       ''],
        ['schema_org_type',      'SoftwareApplication'],
        ['sitemap_enabled',      '1'],
        ['robots_index',         '1'],
        ['canonical_url',        ''],
        ['gtm_id',               ''],
        ['fb_pixel',             ''],
        ['hreflang',             'en-US'],
        // Rate limiting
        ['ai_daily_limit_free',  '20'],
        ['ai_daily_limit_pro',   '200'],
        // Stripe webhook secret
        ['stripe_webhook_secret',''],
        // Cron email toggles
        ['checkin_reminders_enabled', '1'],
        // AI Router settings (Phase 1)
        ['ai_provider_free',     ''],        // override cheap provider for free users
        ['ai_fallback_enabled',  '1'],       // enable provider fallback
        // Emotional intelligence (Phase 2)
        ['emotion_detection_enabled', '1'],  // enable real-time emotion detection
        ['crisis_log_enabled',   '1'],       // log crisis events to audit_log
        ['nudge_enabled',        '1'],       // enable smart emotional nudges
        // Advanced Memory System (Phase 3)
        ['cohere_api_key',       ''],        // Cohere embeddings key
        ['cohere_embed_model',   'embed-english-light-v3.0'],
        ['memory_enabled',       '1'],       // enable advanced memory system
        ['memory_compress_enabled', '1'],    // enable nightly session compression
        ['memory_max_context',   '8'],       // max episodes injected into prompt
        // Voice & Realtime (Phase 4)
        ['gemini_live_enabled',  '0'],       // Gemini Live voice (opt-in)
        ['voice_journaling_enabled', '0'],   // voice journaling feature
        // Behavioral Retention (Phase 5)
        ['rituals_enabled',            '1'],  // enable daily rituals
        ['growth_dashboard_enabled',   '1'],  // enable growth dashboard
        ['analytics_snapshots_enabled','1'],  // nightly analytics snapshots
        ['reminder_engine_enabled',    '1'],  // adaptive reminder engine
        ['encryption_salt',            ''],   // Phase 7 encryption salt
    ];
    $ins = $pdo->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)");
    foreach ($new as [$k,$v]) $ins->execute([$k,$v]);

    // ── SEARCH POWER-UP: FTS5 (Full-Text Search) ─────────────────────────
    // For Blog Posts
    $pdo->exec("CREATE VIRTUAL TABLE IF NOT EXISTS blog_search USING fts5(title, excerpt, content, content='blog_posts', content_rowid='id')");
    $pdo->exec("
    CREATE TRIGGER IF NOT EXISTS blog_search_insert AFTER INSERT ON blog_posts BEGIN
      INSERT INTO blog_search(rowid, title, excerpt, content) VALUES (new.id, new.title, new.excerpt, new.content);
    END;
    CREATE TRIGGER IF NOT EXISTS blog_search_delete AFTER DELETE ON blog_posts BEGIN
      INSERT INTO blog_search(blog_search, rowid, title, excerpt, content) VALUES('delete', old.id, old.title, old.excerpt, old.content);
    END;
    CREATE TRIGGER IF NOT EXISTS blog_search_update AFTER UPDATE ON blog_posts BEGIN
      INSERT INTO blog_search(blog_search, rowid, title, excerpt, content) VALUES('delete', old.id, old.title, old.excerpt, old.content);
      INSERT INTO blog_search(rowid, title, excerpt, content) VALUES (new.id, new.title, new.excerpt, new.content);
    END;
    ");

    // For Memories
    $pdo->exec("CREATE VIRTUAL TABLE IF NOT EXISTS memory_search USING fts5(summary, themes, content='coach_memory', content_rowid='id')");
    $pdo->exec("
    CREATE TRIGGER IF NOT EXISTS memory_search_insert AFTER INSERT ON coach_memory BEGIN
      INSERT INTO memory_search(rowid, summary, themes) VALUES (new.id, new.summary, new.themes);
    END;
    CREATE TRIGGER IF NOT EXISTS memory_search_delete AFTER DELETE ON coach_memory BEGIN
      INSERT INTO memory_search(memory_search, rowid, summary, themes) VALUES('delete', old.id, old.summary, old.themes);
    END;
    CREATE TRIGGER IF NOT EXISTS memory_search_update AFTER UPDATE ON coach_memory BEGIN
      INSERT INTO memory_search(memory_search, rowid, summary, themes) VALUES('delete', old.id, old.summary, old.themes);
      INSERT INTO memory_search(rowid, summary, themes) VALUES (new.id, new.summary, new.themes);
    END;
    ");

    // Phase 3 — Advanced Memory tables
    require_once __DIR__ . '/memory.php';
    memory_run_migrations($pdo);

    // Phase 4 — Voice & Realtime tables
    require_once __DIR__ . '/voice.php';
    voice_run_migrations($pdo);

    // Phase 5 — Behavioral Retention System
    require_once __DIR__ . '/retention.php';
    retention_run_migrations($pdo);
}

/**
 * Perform a ranked Full-Text Search using FTS5.
 * $table: 'blog' or 'memory'
 */
function db_search(string $type, string $query, int $limit = 10): array {
    $vTable = ($type === 'blog') ? 'blog_search' : 'memory_search';
    $source = ($type === 'blog') ? 'blog_posts' : 'coach_memory';
    
    // Ranked search using bm25 scoring
    $sql = "SELECT s.*, rank 
            FROM $source s 
            JOIN $vTable v ON s.id = v.rowid 
            WHERE $vTable MATCH ? 
            ORDER BY rank 
            LIMIT ?";
    return db_query($sql, [$query, $limit]);
}
