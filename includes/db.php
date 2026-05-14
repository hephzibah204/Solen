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
        last_login  TEXT,
        last_ip     TEXT
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
        ('stripe_portal_url', '#'),
        ('stripe_pk',        ''),
        ('stripe_sk',        ''),
        ('google_analytics', ''),
        ('footer_text',      '© 2026 Solen Inc. All rights reserved.');

    CREATE INDEX IF NOT EXISTS idx_subs_status_cycle ON subscriptions(status, billing_cycle);
    CREATE INDEX IF NOT EXISTS idx_coach_msgs_user_date ON coach_messages(user_id, created_at);
    CREATE INDEX IF NOT EXISTS idx_users_last_login ON users(last_login);
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
    // Priority 1: Environment Variables (e.g. from .env or server config)
    $envKey = strtoupper($key);
    $val = getenv($envKey);
    if ($val !== false && $val !== '') return (string)$val;
    // Also check $_ENV superglobal (populated by some SAPI configurations)
    if (!empty($_ENV[$envKey])) return (string)$_ENV[$envKey];

    // Priority 2: Admin Database Settings
    $row = db_one("SELECT value FROM settings WHERE key=?", [$key]);
    return $row ? $row['value'] : $default;
}

function set_setting(string $key, string $value): void {
    db_run("INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value=excluded.value", [$key, $value]);
}

/**
 * get_api_key — Canonical API key resolver.
 *
 * Priority:
 *   1. Environment variable  (getenv / $_ENV)  — e.g. GEMINI_API_KEY in .env or server config
 *   2. Admin database setting                   — e.g. set via Admin → Settings
 *   3. PHP constant from config.php             — e.g. define('GEMINI_API_KEY', '...')
 *
 * Usage: $key = get_api_key('gemini_api_key', 'GEMINI_API_KEY');
 */
function get_api_key(string $settingKey, string $constantName = ''): string {
    // 1. Environment variable (highest priority — server/deployment config)
    $envKey = strtoupper($settingKey);
    $fromEnv = getenv($envKey);
    if ($fromEnv !== false && $fromEnv !== '') return $fromEnv;
    if (!empty($_ENV[$envKey])) return $_ENV[$envKey];

    // 2. Admin DB setting (configured via admin panel)
    $row = db_one("SELECT value FROM settings WHERE key=?", [$settingKey]);
    if ($row && !empty($row['value'])) return $row['value'];

    // 3. PHP constant from config.php (fallback for legacy deployments)
    if ($constantName && defined($constantName)) {
        $v = constant($constantName);
        if ($v !== '') return (string)$v;
    }

    return '';
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
        ['huggingface_model',    'Qwen/Qwen2.5-72B-Instruct'],
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

    // Phase 6 — Family Sharing, Addiction Recovery, Streak Learning
    _run_phase6_migrations($pdo);

    // New login alert IP tracking
    try { $pdo->exec("ALTER TABLE users ADD COLUMN last_ip TEXT"); } catch (PDOException $e) {}

    // Phase 9 — PWA Push Notifications
    _run_phase9_migrations($pdo);
}

function _run_phase9_migrations(PDO $pdo): void {
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS user_push_subscriptions (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        endpoint    TEXT UNIQUE NOT NULL,
        p256dh      TEXT NOT NULL,
        auth        TEXT NOT NULL,
        created_at  TEXT DEFAULT (datetime('now'))
    );
    ");

    // Initialize VAPID keys for PWA Push
    $ins = $pdo->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)");
    $ins->execute(['vapid_public_key', '']);
    $ins->execute(['vapid_private_key', '']);
    $ins->execute(['push_enabled', '0']);
}

function _run_phase6_migrations(PDO $pdo): void {
    // ── Family sharing ────────────────────────────────────────────────────
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS family_groups (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        owner_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        name        TEXT    NOT NULL DEFAULT 'My Family',
        invite_code TEXT    UNIQUE NOT NULL,
        created_at  TEXT    DEFAULT (datetime('now'))
    );
    CREATE INDEX IF NOT EXISTS idx_family_groups_owner ON family_groups(owner_id);

    CREATE TABLE IF NOT EXISTS family_members (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        group_id   INTEGER NOT NULL REFERENCES family_groups(id) ON DELETE CASCADE,
        user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        role       TEXT    NOT NULL DEFAULT 'member',
        joined_at  TEXT    DEFAULT (datetime('now')),
        UNIQUE(group_id, user_id)
    );
    CREATE INDEX IF NOT EXISTS idx_family_members_group ON family_members(group_id);
    CREATE INDEX IF NOT EXISTS idx_family_members_user  ON family_members(user_id);
    ");

    // ── addiction_focus on coach_profiles ─────────────────────────────────
    $cpCols = array_column($pdo->query("PRAGMA table_info(coach_profiles)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('addiction_focus', $cpCols)) {
        $pdo->exec("ALTER TABLE coach_profiles ADD COLUMN addiction_focus TEXT DEFAULT ''");
    }

    // ── Streak Learning (articles + quizzes) ──────────────────────────────
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS streak_articles (
        id           INTEGER PRIMARY KEY AUTOINCREMENT,
        title        TEXT NOT NULL,
        slug         TEXT UNIQUE NOT NULL,
        category     TEXT NOT NULL DEFAULT 'wellness',
        content      TEXT NOT NULL,
        read_time_min INTEGER DEFAULT 3,
        created_at   TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS streak_quizzes (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        article_id    INTEGER NOT NULL REFERENCES streak_articles(id) ON DELETE CASCADE,
        question      TEXT NOT NULL,
        options       TEXT NOT NULL DEFAULT '[]',
        correct_index INTEGER NOT NULL DEFAULT 0,
        explanation   TEXT DEFAULT ''
    );

    CREATE TABLE IF NOT EXISTS streak_user_progress (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        article_id      INTEGER NOT NULL REFERENCES streak_articles(id) ON DELETE CASCADE,
        read_at         TEXT,
        quiz_completed  INTEGER DEFAULT 0,
        quiz_score      INTEGER DEFAULT 0,
        points_earned   INTEGER DEFAULT 0,
        created_at      TEXT DEFAULT (datetime('now')),
        UNIQUE(user_id, article_id)
    );
    CREATE INDEX IF NOT EXISTS idx_streak_progress_user ON streak_user_progress(user_id);
    ");

    // ── Settings for Phase 6 ──────────────────────────────────────────────
    $ins = $pdo->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)");
    $ins->execute(['family_max_members', '4']);
    $ins->execute(['streak_learning_enabled', '1']);

    // ── Seed articles if none exist ───────────────────────────────────────
    $count = $pdo->query("SELECT COUNT(*) FROM streak_articles")->fetchColumn();
    if ((int)$count === 0) {
        _seed_streak_articles($pdo);
    }
}

function _seed_streak_articles(PDO $pdo): void {
    $articles = [
        [
            'wellness',
            'The Science of Micro-Habits',
            'micro-habits',
            3,
            "Small actions repeated daily are the foundation of lasting change. Research from University College London shows it takes an average of 66 days — not 21 — for a habit to become automatic. The key insight: the size of the habit doesn't determine how sticky it becomes. A 2-minute morning stretch and a 2-hour workout have similar habit-formation timelines. Start small. Stack new habits onto existing ones (habit stacking). Celebrate completion immediately to trigger dopamine.\n\nPractical steps:\n• Pick one habit under 2 minutes\n• Attach it to something you already do\n• Track it for 7 days before adding another",
            [
                ['What does research say is the average time to form a habit?', ['7 days', '21 days', '66 days', '90 days'], 2, '66 days is the average found by UCL research, though it varies by person and habit complexity.'],
                ['What is "habit stacking"?', ['Doing many habits at once', 'Attaching a new habit to an existing one', 'Repeating a habit until it sticks', 'Tracking habits in a journal'], 1, 'Habit stacking links a new behaviour to an already-established one, using the existing routine as a cue.'],
            ],
        ],
        [
            'anxiety',
            'Understanding Your Anxiety Response',
            'understanding-anxiety',
            4,
            "Anxiety is your nervous system doing its job — sometimes too well. The amygdala, your brain's alarm centre, fires before the rational prefrontal cortex can evaluate the actual threat. This is why anxiety feels so physical: racing heart, shallow breath, tight chest. These are your body preparing to run or fight.\n\nThe problem is modern threats — deadlines, social pressure, uncertainty — don't resolve with sprinting. The energy stays trapped. Techniques like box breathing (4s inhale, 4s hold, 4s exhale, 4s hold) directly activate the parasympathetic nervous system, signalling safety.\n\nKey insight: you cannot think your way out of anxiety during a peak. You have to breathe or move first, then think.",
            [
                ['Which brain region triggers the anxiety alarm?', ['Prefrontal cortex', 'Hippocampus', 'Amygdala', 'Cerebellum'], 2, 'The amygdala is the brain\'s threat-detection centre and fires before rational thought can intervene.'],
                ['What does box breathing do to the nervous system?', ['Increases adrenaline', 'Activates the fight-or-flight response', 'Activates the parasympathetic (calm) response', 'Shuts down the amygdala permanently'], 2, 'Box breathing stimulates the vagus nerve, activating the parasympathetic nervous system — the body\'s "rest and digest" mode.'],
            ],
        ],
        [
            'addiction',
            'Understanding Cravings: The HALT Method',
            'cravings-halt-method',
            3,
            "Cravings rarely appear from nowhere. Recovery specialists use the HALT acronym to identify the most common underlying triggers:\n\n• Hungry — low blood sugar affects mood and willpower\n• Angry — unprocessed frustration seeks release\n• Lonely — connection needs are powerful and often unconscious\n• Tired — fatigue depletes the prefrontal cortex's control\n\nWhen a craving hits, pause and ask: am I HALT right now? Addressing the root need often reduces the craving's intensity significantly. Eat something. Process the anger with writing or movement. Reach out to one safe person. Rest.\n\nCravings are time-limited — most peak within 15–30 minutes and then subside if not acted on.",
            [
                ['What does HALT stand for?', ['Happy, Anxious, Lost, Tired', 'Hungry, Angry, Lonely, Tired', 'Hopeless, Angry, Longing, Triggered', 'Hungry, Aware, Lonely, Tense'], 1, 'HALT is a recovery tool: Hungry, Angry, Lonely, Tired — the four most common craving triggers.'],
                ['How long do most cravings peak for if not acted on?', ['2–5 minutes', '1 hour', '15–30 minutes', 'Several hours'], 2, 'Most cravings peak within 15–30 minutes and subside naturally — the urge-surfing technique uses this.'],
            ],
        ],
        [
            'sleep',
            'Why Sleep Is Your Emotional Reset Button',
            'sleep-emotional-reset',
            3,
            "During REM sleep, your brain replays emotional memories but strips away the stress response — a process called emotional memory reconsolidation. This is why things that feel catastrophic at 2am seem manageable in the morning.\n\nSleep deprivation amplifies amygdala reactivity by up to 60% (Walker, 2017). This means poor sleep makes you more reactive, more anxious, and less able to regulate mood.\n\nCircadian rhythm tips:\n• Same sleep/wake time every day (even weekends)\n• No screens 30 minutes before bed\n• Keep the bedroom cool (65–68°F / 18–20°C)\n• Avoid caffeine after 2pm",
            [
                ['By how much can sleep deprivation amplify amygdala reactivity?', ['10%', '30%', '60%', '100%'], 2, 'Matthew Walker\'s research found sleep deprivation increases amygdala reactivity by up to 60%, making emotional regulation much harder.'],
                ['What sleep process strips stress from emotional memories?', ['Deep sleep compression', 'REM emotional reconsolidation', 'Sleep spindle processing', 'Cortisol flushing'], 1, 'REM sleep replays emotional memories while removing the associated stress response, which is why morning perspectives differ from nighttime ones.'],
            ],
        ],
        [
            'wellness',
            'The Power of Daily Reflection',
            'daily-reflection',
            3,
            "Journaling and daily reflection are among the most evidence-backed wellness practices. In studies by James Pennebaker, writing about difficult experiences for just 15 minutes a day over 4 days reduced anxiety, improved immune function, and increased subjective wellbeing.\n\nReflection works by converting raw experience into narrative — your brain can then file it as 'processed' rather than holding it on alert. It also builds self-awareness, which is the foundation of emotional intelligence.\n\nYou don't need to write perfectly. Three prompts:\n• What happened today?\n• How did I feel?\n• What would I do differently?",
            [
                ['What did Pennebaker\'s research find about expressive writing?', ['It increased anxiety', 'It improved immune function and wellbeing', 'It had no measurable effect', 'It only helped people with depression'], 1, 'Pennebaker\'s studies found that writing about difficult experiences reduced anxiety and improved immune markers after just 4 days.'],
                ['Why does writing about experience help the brain?', ['It distracts from problems', 'It converts raw experience into processed narrative', 'It strengthens memory', 'It reduces cortisol directly'], 1, 'Narrative processing signals to the brain that an experience has been addressed, reducing the ongoing vigilance response.'],
            ],
        ],
        [
            'growth',
            'Fixed vs Growth Mindset in Recovery & Wellness',
            'fixed-vs-growth-mindset',
            4,
            "Carol Dweck's research on mindset has profound implications for wellness. A fixed mindset says: 'I'm either this way or I'm not.' A growth mindset says: 'I can develop through effort and learning.'\n\nIn recovery contexts, fixed mindset sounds like: 'I always fail, I'm just an addict.' Growth mindset sounds like: 'This was a difficult moment. What can I learn from it?'\n\nFixed mindset makes setbacks feel permanent and personal. Growth mindset treats them as information. This single shift has been shown to improve resilience, persistence, and recovery outcomes.\n\nPractice: Next time something goes wrong, ask 'What is this teaching me?' instead of 'What does this say about me?'",
            [
                ['What is the core difference between fixed and growth mindset?', ['Intelligence vs emotion', 'Belief that abilities are static vs developable', 'Optimism vs pessimism', 'External vs internal focus'], 1, 'Fixed mindset believes abilities are innate and static. Growth mindset believes they can be developed through effort.'],
                ['How should a growth mindset frame a setback?', ['As proof of failure', 'As permanent and personal', 'As information to learn from', 'As someone else\'s fault'], 2, 'A growth mindset treats setbacks as learning data, not as evidence of being fundamentally flawed.'],
            ],
        ],
        [
            'relationships',
            'Healthy Boundaries: What They Actually Are',
            'healthy-boundaries',
            3,
            "Boundaries are not walls — they're guidelines about what you need to feel safe and respected. They're communicated, not enforced silently and then resented.\n\nA common misconception: boundaries control others. They don't. Boundaries define your response: 'If X happens, I will do Y.' You can't control whether someone respects your boundary, but you can control your response when they don't.\n\nTypes of boundaries:\n• Physical (personal space, touch)\n• Emotional (what you share, with whom, when)\n• Time (availability, commitments)\n• Digital (response times, sharing)\n\nHealthy boundary formula: 'When [X happens], I feel [Y], and I need [Z].'",
            [
                ['What is the most common misconception about boundaries?', ['That they are rude', 'That they control other people\'s behaviour', 'That they require confrontation', 'That they only apply to strangers'], 1, 'Boundaries define your own response, not someone else\'s behaviour — you cannot control whether someone respects them.'],
                ['Complete the healthy boundary formula: "When X happens, I feel Y, and..."', ['...you should stop.', '...I need Z.', '...that\'s not okay.', '...we have a problem.'], 1, '"When [X], I feel [Y], and I need [Z]" is a non-blaming formula that expresses needs clearly.'],
            ],
        ],
        [
            'anxiety',
            'Grounding Techniques That Actually Work',
            'grounding-techniques',
            3,
            "Grounding brings you from anxious future-thinking back to the present moment. These techniques work because anxiety lives in the future; your senses only exist now.\n\n5-4-3-2-1 Technique:\n• 5 things you can see\n• 4 things you can physically feel\n• 3 things you can hear\n• 2 things you can smell\n• 1 thing you can taste\n\nPhysical grounding:\n• Hold ice (intense sensation overrides anxiety)\n• Splash cold water on your face\n• Press feet firmly into the ground, feel the pressure\n\nCognitive grounding:\n• Name 5 things in your favourite colour\n• Count backwards from 100 by 7s\n• Recite song lyrics you know well",
            [
                ['Why does the 5-4-3-2-1 technique work for anxiety?', ['It distracts the mind', 'Anxiety lives in the future; sensory input exists only in the present', 'It reduces cortisol chemically', 'It activates the fight-or-flight response'], 1, 'Engaging the senses forces present-moment awareness, which interrupts the future-oriented thought loop of anxiety.'],
                ['Which physical grounding technique uses intense sensation to override anxiety?', ['Deep breathing', 'Holding ice', 'Meditation', 'Yoga'], 1, 'The intense cold sensation of holding ice is so strong it redirects the nervous system\'s attention, reducing anxiety acutely.'],
            ],
        ],
        [
            'wellness',
            'Movement as Medicine for Mental Health',
            'movement-mental-health',
            4,
            "Exercise is one of the most evidence-backed mental health interventions available. A meta-analysis of 1,039 trials (Singh et al., 2023) found exercise was more effective than medication or therapy for depression and anxiety in the short term.\n\nWhy it works:\n• BDNF (brain-derived neurotrophic factor) — exercise increases this 'miracle-gro for the brain', promoting neuroplasticity\n• Endocannabinoids — these (not endorphins) cause the runner's high\n• Stress hormone regulation — physical exertion burns off cortisol and adrenaline\n• Sleep quality improvement — tires the body naturally\n\nYou don't need a gym. 20 minutes of brisk walking 3x per week produces measurable mood benefits within 2 weeks.",
            [
                ['What brain chemical does exercise increase, supporting neuroplasticity?', ['Serotonin', 'Dopamine', 'BDNF', 'Melatonin'], 2, 'BDNF (Brain-Derived Neurotrophic Factor) is increased by exercise and promotes the growth of new neural connections.'],
                ['How much walking produces measurable mood benefits within 2 weeks?', ['5 minutes daily', '1 hour daily', '20 minutes 3x per week', '45 minutes every day'], 2, 'Research shows 20 minutes of brisk walking 3 times per week is sufficient to produce measurable mood improvements.'],
            ],
        ],
        [
            'addiction',
            'The Reward Circuit: Why Habits Feel Compulsive',
            'reward-circuit-habits',
            4,
            "All addictions share a common mechanism: the brain's dopamine reward circuit. Dopamine isn't the pleasure chemical — it's the anticipation and wanting chemical. It fires in response to cues that predict reward, not the reward itself.\n\nThis is why:\n• Seeing a wine bottle triggers craving before any sip\n• Opening Instagram produces anticipation before any scroll\n• Walking past a casino feels magnetic even years into recovery\n\nThe circuit learns cues through repetition. Recovery involves building new associations — new cues that trigger healthy reward pathways. This takes time because the original pathways don't disappear; they're just gradually overwritten by stronger new ones.\n\nEvery day you choose differently, you're literally rewiring your brain.",
            [
                ['What does dopamine actually signal in the brain?', ['Pleasure and satisfaction', 'Anticipation and wanting', 'Safety and calm', 'Memory formation'], 1, 'Dopamine is the anticipation chemical — it fires in response to cues that predict reward, driving the seeking behaviour.'],
                ['What happens to old addiction pathways in recovery?', ['They are permanently deleted', 'They are gradually overwritten by stronger new pathways', 'They remain unchanged forever', 'They shrink after 30 days'], 1, 'Old neural pathways don\'t disappear — recovery works by building stronger new pathways that gradually take precedence.'],
            ],
        ],
        [
            'growth',
            'Emotional Intelligence: The Four Quadrants',
            'eq-four-quadrants',
            4,
            "Emotional Intelligence (EQ) is the ability to recognize, understand, and manage your own emotions while recognizing, understanding, and influencing the emotions of others. Daniel Goleman's model breaks this down into four quadrants:\n\n1. Self-Awareness (Internal/Recognition): Knowing what you're feeling and why.\n2. Self-Management (Internal/Regulation): Staying calm and thinking clearly under pressure.\n3. Social Awareness (External/Recognition): Empathy and reading the room.\n4. Relationship Management (External/Regulation): Communicating clearly and resolving conflict.\n\nUnlike IQ, which is relatively static, EQ is highly developable. High EQ is a stronger predictor of career success and relationship satisfaction than technical skill.",
            [
                ['What are the two internal quadrants of Emotional Intelligence?', ['Self-Awareness and Self-Management', 'Social Awareness and Empathy', 'Relationship Management and Logic', 'Self-Awareness and Social Awareness'], 0, 'Self-Awareness and Self-Management deal with your internal state.'],
                ['Is Emotional Intelligence (EQ) static or developable?', ['It never changes', 'It is highly developable through practice', 'It only changes in childhood', 'It decreases with age'], 1, 'Unlike IQ, EQ is a skill set that can be improved throughout life with intentional practice.'],
            ],
        ],
        [
            'mindfulness',
            'The Default Mode Network and Mindfulness',
            'dmn-mindfulness',
            5,
            "The Default Mode Network (DMN) is a collection of brain regions that are active when you aren't focused on the outside world. It's associated with mind-wandering, rumination, and thinking about the self (past/future).\n\nAn overactive DMN is strongly correlated with depression and anxiety. Mindfulness meditation has been shown in fMRI studies to 'quiet' the DMN, shifting activity to the Task Positive Network (TPN) — the network associated with the present moment and external focus.\n\nPracticing presence literally changes the wiring of your brain, reducing the tendency for negative self-talk and increasing focus. Just 10 minutes of focus on the breath can effectively down-regulate the DMN for hours.",
            [
                ['When is the Default Mode Network (DMN) most active?', ['When you are solving a hard puzzle', 'When your mind is wandering or ruminating', 'When you are sleeping deeply', 'When you are intensely focused on a task'], 1, 'The DMN is the brain\'s "idle" state, active during mind-wandering and self-referential thought.'],
                ['How does mindfulness affect the DMN?', ['It makes it more active', 'It has no effect', 'It quiets the DMN and shifts focus to the Task Positive Network', 'It deletes the network'], 2, 'Mindfulness reduces DMN activity, helping to break cycles of rumination and self-criticism.'],
            ],
        ],
    ];

    $artStmt  = $pdo->prepare("INSERT OR IGNORE INTO streak_articles (category, title, slug, read_time_min, content) VALUES (?,?,?,?,?)");
    $quizStmt = $pdo->prepare("INSERT INTO streak_quizzes (article_id, question, options, correct_index, explanation) VALUES (?,?,?,?,?)");

    foreach ($articles as [$cat, $title, $slug, $readTime, $content, $quizzes]) {
        $artStmt->execute([$cat, $title, $slug, $readTime, $content]);
        $artId = $pdo->lastInsertId();
        if (!$artId) {
            $row   = $pdo->prepare("SELECT id FROM streak_articles WHERE slug=?");
            $row->execute([$slug]);
            $artId = $row->fetchColumn();
        }
        foreach ($quizzes as [$question, $options, $correctIdx, $explanation]) {
            $quizStmt->execute([$artId, $question, json_encode($options), $correctIdx, $explanation]);
        }
    }
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
