<?php
/**
 * Solen Advanced Memory System — Phase 3
 *
 * Covers:
 *   #1  Semantic Memory  — embedding-based similarity search via Cohere
 *   #2  Episodic Memory  — breakthroughs, conflicts, triggers, life moments
 *   #3  Emotional Memory — anxiety cycles, burnout patterns, recovery tracking
 *   #4  Memory Ranking   — priority scoring by importance × recency × intensity
 *
 * Public API:
 *   memory_store_episode($userId, $text, $type, $opts)  → int  (episode id)
 *   memory_search_semantic($userId, $query, $limit)     → array
 *   memory_get_ranked($userId, $limit, $filter)         → array
 *   memory_build_context($userId, $currentText)         → string  (prompt block)
 *   memory_track_emotional_pattern($userId, $state, $score) → void
 *   memory_get_emotional_patterns($userId)              → array
 *   memory_compress_session($userId, $sessionDate)      → void  (background)
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/prompt_engine.php';

// ── CONSTANTS ─────────────────────────────────────────────────────────────

/**
 * Episode types — each maps to a different storage priority weight.
 */
const MEMORY_TYPE_BREAKTHROUGH   = 'breakthrough';   // high priority
const MEMORY_TYPE_CONFLICT       = 'conflict';        // high priority
const MEMORY_TYPE_TRIGGER        = 'trigger';         // high priority
const MEMORY_TYPE_LIFE_MOMENT    = 'life_moment';     // high priority
const MEMORY_TYPE_RECOVERY       = 'recovery';        // medium
const MEMORY_TYPE_GOAL           = 'goal';            // medium
const MEMORY_TYPE_SESSION        = 'session';         // base (auto-generated)

const MEMORY_TYPE_WEIGHTS = [
    MEMORY_TYPE_BREAKTHROUGH => 1.0,
    MEMORY_TYPE_CONFLICT     => 0.9,
    MEMORY_TYPE_TRIGGER      => 0.9,
    MEMORY_TYPE_LIFE_MOMENT  => 0.85,
    MEMORY_TYPE_RECOVERY     => 0.7,
    MEMORY_TYPE_GOAL         => 0.65,
    MEMORY_TYPE_SESSION      => 0.4,
];

// Emotional pattern labels for memory tracking
const EMOTIONAL_PATTERN_LABELS = [
    'anxiety_cycle'   => 'Recurring anxiety pattern',
    'burnout_episode' => 'Burnout episode',
    'recovery_step'   => 'Recovery milestone',
    'isolation'       => 'Social withdrawal period',
    'mood_crash'      => 'Sudden mood drop',
    'resilience'      => 'Emotional resilience shown',
];

// ── SCHEMA MIGRATIONS (called from db.php run_migrations) ─────────────────

/**
 * Called by db.php's run_migrations() to add Phase 3 tables.
 * Safe to call multiple times (all IF NOT EXISTS).
 */
function memory_run_migrations(PDO $pdo): void {

    // ── Episodic Memory ────────────────────────────────────────────────────
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS memory_episodes (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        type            TEXT NOT NULL DEFAULT 'session',
        summary         TEXT NOT NULL,
        raw_text        TEXT,
        embedding       TEXT,             -- JSON float array from Cohere
        embedding_model TEXT DEFAULT 'embed-english-light-v3.0',
        importance      REAL DEFAULT 0.5, -- 0.0 → 1.0, computed by rank engine
        emotional_score REAL DEFAULT 0.3, -- emotional intensity at time of storage
        recurrence      INTEGER DEFAULT 0,-- how many times this theme has appeared
        tags            TEXT DEFAULT '[]',-- JSON array
        session_date    TEXT NOT NULL DEFAULT (date('now')),
        created_at      TEXT DEFAULT (datetime('now')),
        updated_at      TEXT DEFAULT (datetime('now'))
    );
    CREATE INDEX IF NOT EXISTS idx_episodes_user     ON memory_episodes(user_id, session_date);
    CREATE INDEX IF NOT EXISTS idx_episodes_type     ON memory_episodes(user_id, type);
    CREATE INDEX IF NOT EXISTS idx_episodes_rank     ON memory_episodes(user_id, importance DESC);
    ");

    // ── Emotional Pattern History ──────────────────────────────────────────
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS memory_emotional_patterns (
        id           INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        pattern      TEXT NOT NULL,       -- see EMOTIONAL_PATTERN_LABELS
        description  TEXT,               -- AI-generated description of the pattern
        intensity    REAL DEFAULT 0.5,
        occurrence   INTEGER DEFAULT 1,  -- times this pattern has been detected
        first_seen   TEXT DEFAULT (date('now')),
        last_seen    TEXT DEFAULT (date('now')),
        resolved_at  TEXT                -- nullable — when pattern was resolved
    );
    CREATE INDEX IF NOT EXISTS idx_patterns_user ON memory_emotional_patterns(user_id, pattern);
    ");

    // ── Memory Access Log (for recency weighting) ─────────────────────────
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS memory_access_log (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        episode_id  INTEGER NOT NULL REFERENCES memory_episodes(id) ON DELETE CASCADE,
        user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        accessed_at TEXT DEFAULT (datetime('now'))
    );
    CREATE INDEX IF NOT EXISTS idx_access_episode ON memory_access_log(episode_id);
    ");
}

// ── EPISODIC MEMORY STORAGE ───────────────────────────────────────────────

/**
 * Store a new memory episode.
 *
 * @param int    $userId
 * @param string $summary    Human-readable summary (stored + ranked)
 * @param string $type       One of MEMORY_TYPE_* constants
 * @param array  $opts       raw_text, emotional_score, tags[], session_date, generate_embedding
 * @return int   episode id
 */
function memory_store_episode(int $userId, string $summary, string $type = MEMORY_TYPE_SESSION, array $opts = []): int {
    $emotionalScore = (float)($opts['emotional_score'] ?? 0.3);
    $tags           = json_encode($opts['tags'] ?? []);
    $sessionDate    = $opts['session_date'] ?? date('Y-m-d');
    $rawText        = $opts['raw_text'] ?? '';
    $typeWeight     = MEMORY_TYPE_WEIGHTS[$type] ?? 0.4;

    // Initial importance = type weight × emotional intensity
    $importance = min(1.0, $typeWeight * (0.5 + $emotionalScore * 0.5));

    // Phase 7 — Encrypt sensitive content before storage
    $encryptedSummary = _memory_encrypt($userId, $summary);
    $encryptedRaw     = $rawText ? _memory_encrypt($userId, $rawText) : null;

    $id = db_run(
        "INSERT INTO memory_episodes
            (user_id, type, summary, raw_text, importance, emotional_score, tags, session_date)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        [$userId, $type, $encryptedSummary, $encryptedRaw, $importance, $emotionalScore, $tags, $sessionDate]
    );

    // Check for recurrence of similar themes — bump importance if pattern repeats
    _memory_update_recurrence($userId, $id, $summary, $type);

    // Generate embedding asynchronously (or inline if forced)
    if ($opts['generate_embedding'] ?? true) {
        _memory_generate_embedding($id, $summary);
    }

    // Phase 10 — Evolve the AI Companion relationship dynamics
    memory_evolve_companion($userId);

    return $id;
}

/**
 * Detect if the same theme has appeared before and update recurrence counter.
 */
function _memory_update_recurrence(int $userId, int $newId, string $summary, string $type): void {
    // Find recent episodes of same type
    $recent = db_query(
        "SELECT id, summary, recurrence FROM memory_episodes
         WHERE user_id=? AND type=? AND id!=?
         ORDER BY created_at DESC LIMIT 10",
        [$userId, $type, $newId]
    );

    $keywords = _extract_keywords($summary);
    if (empty($keywords)) return;

    foreach ($recent as $ep) {
        $epKeywords = _extract_keywords($ep['summary']);
        $overlap    = count(array_intersect($keywords, $epKeywords));
        $similarity = $overlap / max(1, min(count($keywords), count($epKeywords)));

        if ($similarity >= 0.4) {
            // Same theme seen again — increment recurrence on both
            $newRecurrence = (int)$ep['recurrence'] + 1;
            db_run(
                "UPDATE memory_episodes SET recurrence=?, updated_at=datetime('now') WHERE id=?",
                [$newRecurrence, $ep['id']]
            );
            db_run(
                "UPDATE memory_episodes SET recurrence=?, updated_at=datetime('now') WHERE id=?",
                [$newRecurrence, $newId]
            );
            break;
        }
    }

    // Recompute importance after recurrence update
    _memory_rerank_episode($newId);
}

/**
 * Extract simple keywords from text for fuzzy theme matching.
 */
function _extract_keywords(string $text): array {
    $stopWords = ['the','a','an','is','was','were','i','my','me','you','your','we','and',
                  'but','or','so','it','its','this','that','to','of','in','on','at','for',
                  'with','about','been','have','has','had','do','did','not','no','be'];
    $words = preg_split('/\W+/', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
    return array_values(array_filter($words, fn($w) => strlen($w) > 3 && !in_array($w, $stopWords)));
}

// ── SEMANTIC MEMORY (Cohere Embeddings) ───────────────────────────────────

/**
 * Generate and store embedding for an episode via Cohere.
 * Falls back gracefully if Cohere key is missing.
 */
function _memory_generate_embedding(int $episodeId, string $text): void {
    $apiKey = get_setting('cohere_api_key') ?: (defined('COHERE_API_KEY') ? COHERE_API_KEY : '');
    if (!$apiKey || strlen(trim($text)) < 10) return;

    $model = get_setting('cohere_embed_model', 'embed-english-light-v3.0');

    $ch = curl_init('https://api.cohere.ai/v1/embed');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'texts'      => [mb_substr($text, 0, 512)],
            'model'      => $model,
            'input_type' => 'search_document',
        ]),
    ]);

    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !$raw) return;

    $data = json_decode($raw, true);
    $embedding = $data['embeddings'][0] ?? null;
    if (!$embedding) return;

    db_run(
        "UPDATE memory_episodes SET embedding=?, embedding_model=? WHERE id=?",
        [json_encode($embedding), $model, $episodeId]
    );
}

/**
 * Semantic search: find memories most similar to $query.
 * Uses cosine similarity if embeddings exist; falls back to keyword matching.
 *
 * @param int    $userId
 * @param string $query   The current user message / search text
 * @param int    $limit
 * @return array  Array of memory_episodes rows, ordered by similarity DESC
 */
function memory_search_semantic(int $userId, string $query, int $limit = 5, bool $fastMode = false): array {
    if ($fastMode) {
        return _memory_keyword_search($userId, $query, $limit);
    }

    $apiKey = get_setting('cohere_api_key') ?: (defined('COHERE_API_KEY') ? COHERE_API_KEY : '');

    // Try embedding-based search first
    if ($apiKey) {
        $queryEmbedding = _memory_embed_query($query, $apiKey);
        if ($queryEmbedding) {
            return _memory_cosine_search($userId, $queryEmbedding, $limit);
        }
    }

    // Fallback: keyword overlap search
    return _memory_keyword_search($userId, $query, $limit);
}

/**
 * Get query embedding from Cohere.
 */
function _memory_embed_query(string $query, string $apiKey): ?array {
    $model = get_setting('cohere_embed_model', 'embed-english-light-v3.0');

    $ch = curl_init('https://api.cohere.ai/v1/embed');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'texts'      => [mb_substr($query, 0, 512)],
            'model'      => $model,
            'input_type' => 'search_query',
        ]),
    ]);

    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !$raw) return null;
    $data = json_decode($raw, true);
    return $data['embeddings'][0] ?? null;
}

/**
 * Cosine similarity search across stored embeddings.
 */
function _memory_cosine_search(int $userId, array $queryVec, int $limit): array {
    // Pull all episodes that have embeddings
    $episodes = db_query(
        "SELECT id, summary, type, importance, emotional_score, session_date, embedding, tags
         FROM memory_episodes WHERE user_id=? AND embedding IS NOT NULL
         ORDER BY created_at DESC LIMIT 200",
        [$userId]
    );

    $scored = [];
    foreach ($episodes as $ep) {
        $vec = json_decode($ep['embedding'] ?? '[]', true);
        if (empty($vec)) continue;
        $sim = _cosine_similarity($queryVec, $vec);
        $summary = _memory_decrypt($userId, $ep['summary']);
        $scored[] = array_merge($ep, ['summary' => $summary, 'similarity' => $sim]);
    }

    usort($scored, fn($a, $b) => $b['similarity'] <=> $a['similarity']);
    return array_slice($scored, 0, $limit);
}

/**
 * Cosine similarity between two float vectors.
 */
function _cosine_similarity(array $a, array $b): float {
    $dot = 0.0; $normA = 0.0; $normB = 0.0;
    $len = min(count($a), count($b));
    for ($i = 0; $i < $len; $i++) {
        $dot   += $a[$i] * $b[$i];
        $normA += $a[$i] * $a[$i];
        $normB += $b[$i] * $b[$i];
    }
    $denom = sqrt($normA) * sqrt($normB);
    return $denom > 0 ? $dot / $denom : 0.0;
}

/**
 * Keyword fallback search (no embedding required).
 */
function _memory_keyword_search(int $userId, string $query, int $limit): array {
    $keywords = _extract_keywords($query);
    if (empty($keywords)) {
        // Return top-ranked memories as fallback
        return memory_get_ranked($userId, $limit);
    }

    $episodes = db_query(
        "SELECT id, summary, type, importance, emotional_score, session_date, tags
         FROM memory_episodes WHERE user_id=?
         ORDER BY importance DESC, created_at DESC LIMIT 100",
        [$userId]
    );

    $scored = [];
    foreach ($episodes as $ep) {
        $summary = _memory_decrypt($userId, $ep['summary']);
        $epKw    = _extract_keywords($summary);
        $overlap = count(array_intersect($keywords, $epKw));
        if ($overlap > 0) {
            $scored[] = array_merge($ep, ['summary' => $summary, 'similarity' => $overlap / max(count($keywords), 1)]);
        }
    }

    usort($scored, fn($a, $b) => $b['similarity'] <=> $a['similarity']);
    return array_slice($scored, 0, $limit);
}

// ── MEMORY RANKING ENGINE ─────────────────────────────────────────────────

/**
 * Memory Ranking Engine — Phase 3 #4
 *
 * Score = (type_weight × 0.35) + (emotional_intensity × 0.30)
 *       + (recency_decay × 0.20) + (recurrence_bonus × 0.15)
 *
 * @param int    $userId
 * @param int    $limit
 * @param string $filter  optional type filter
 * @return array  Sorted by computed priority score DESC
 */
function memory_get_ranked(int $userId, int $limit = 10, string $filter = ''): array {
    $where  = $filter ? "AND type=?" : '';
    $params = $filter ? [$userId, $filter] : [$userId];

    $episodes = db_query(
        "SELECT * FROM memory_episodes WHERE user_id=? {$where}
         ORDER BY created_at DESC LIMIT 500",
        $params
    );

    $now = time();
    foreach ($episodes as &$ep) {
        $ep['summary']        = _memory_decrypt($userId, $ep['summary']);
        $ep['priority_score'] = _memory_compute_priority($ep, $now);
    }

    usort($episodes, fn($a, $b) => $b['priority_score'] <=> $a['priority_score']);
    return array_slice($episodes, 0, $limit);
}

/**
 * Compute the priority score for a single episode.
 */
function _memory_compute_priority(array $ep, int $now): float {
    $typeWeight = MEMORY_TYPE_WEIGHTS[$ep['type']] ?? 0.4;

    // Recency decay: half-life of 30 days
    $createdTs = strtotime($ep['created_at'] ?? 'now');
    $daysSince = max(0, ($now - $createdTs) / 86400);
    $recency   = exp(-$daysSince / 30.0); // 0→1, decays to ~0.37 at 30 days

    // Recurrence bonus: repeated themes are more important
    $recurrence = min(1.0, (int)($ep['recurrence'] ?? 0) / 5.0);

    // Emotional intensity
    $emotional = min(1.0, (float)($ep['emotional_score'] ?? 0.3));

    return ($typeWeight * 0.35)
         + ($emotional  * 0.30)
         + ($recency    * 0.20)
         + ($recurrence * 0.15);
}

/**
 * Recompute and persist the importance score for an episode.
 */
function _memory_rerank_episode(int $episodeId): void {
    $ep = db_one("SELECT * FROM memory_episodes WHERE id=?", [$episodeId]);
    if (!$ep) return;
    $score = _memory_compute_priority($ep, time());
    db_run(
        "UPDATE memory_episodes SET importance=?, updated_at=datetime('now') WHERE id=?",
        [round($score, 4), $episodeId]
    );
}

// ── CONTEXT BUILDER ───────────────────────────────────────────────────────

/**
 * Build a rich memory context block for injection into the system prompt.
 * Called by build_system_prompt() or directly from ai.php.
 *
 * @param int    $userId
 * @param string $currentText   The user's current message (for semantic search)
 * @return string               Ready-to-inject memory block
 */
function memory_build_context(int $userId, string $currentText = '', bool $fastMode = false): string {
    // 1. Always include top-ranked episodic memories
    $ranked = memory_get_ranked($userId, 6);

    // 2. Add semantically relevant memories for current message
    $semantic = [];
    if (strlen(trim($currentText)) > 10) {
        $semantic = memory_search_semantic($userId, $currentText, 3, $fastMode);
        // De-duplicate against ranked
        $rankedIds = array_column($ranked, 'id');
        $semantic  = array_filter($semantic, fn($ep) => !in_array($ep['id'], $rankedIds));
    }

    $allMemories = array_merge($ranked, array_values($semantic));
    if (empty($allMemories)) return '';

    // 3. Build emotional pattern summary
    $patternSummary = _memory_format_emotional_patterns($userId);

    // 4. Format the memory block
    $lines = [];
    foreach ($allMemories as $ep) {
        $date    = $ep['session_date'] ?? '';
        $type    = ucfirst(str_replace('_', ' ', $ep['type']));
        $summary = $ep['summary'] ?? '';
        $tags    = json_decode($ep['tags'] ?? '[]', true);
        $tagStr  = !empty($tags) ? ' [' . implode(', ', $tags) . ']' : '';
        $lines[] = "• [{$type} — {$date}] {$summary}{$tagStr}";
    }

    $block  = "MEMORY — WHAT YOU KNOW ABOUT THIS PERSON:\n";
    $block .= implode("\n", $lines);

    if ($patternSummary) {
        $block .= "\n\nEMOTIONAL PATTERNS OBSERVED:\n{$patternSummary}";
    }

    $block .= "\n\nUse this knowledge naturally to deepen your responses — never recite it robotically or as a list. Reference it when it genuinely adds warmth or insight.";

    return $block;
}

/**
 * Format emotional patterns into a summary string.
 */
function _memory_format_emotional_patterns(int $userId): string {
    $patterns = memory_get_emotional_patterns($userId);
    if (empty($patterns)) return '';

    $lines = [];
    foreach ($patterns as $p) {
        if ($p['resolved_at']) continue; // skip resolved patterns
        $label = EMOTIONAL_PATTERN_LABELS[$p['pattern']] ?? $p['pattern'];
        $times = (int)$p['occurrence'];
        $last  = $p['last_seen'] ?? '';
        $desc  = $p['description'] ?? '';
        $lines[] = "• {$label}" . ($times > 1 ? " (seen {$times}×, last: {$last})" : " (first seen {$last})")
                 . ($desc ? ": {$desc}" : '');
    }

    return implode("\n", $lines);
}

// ── EMOTIONAL PATTERN TRACKING ────────────────────────────────────────────

/**
 * Track a detected emotional state into the pattern history.
 * Called from ai.php after emotion detection.
 *
 * @param int    $userId
 * @param string $state    Detected state from detect_emotion()
 * @param float  $score    Emotional intensity score
 */
function memory_track_emotional_pattern(int $userId, string $state, float $score): void {
    if (in_array($state, ['neutral', 'positive'])) return; // don't track neutral/positive as patterns

    // Map emotion states to pattern labels
    $stateToPattern = [
        'crisis'       => 'mood_crash',
        'high_distress'=> 'mood_crash',
        'burnout'      => 'burnout_episode',
        'anxiety_high' => 'anxiety_cycle',
        'low'          => 'mood_crash',
    ];

    $pattern = $stateToPattern[$state] ?? null;
    if (!$pattern) return;

    $existing = db_one(
        "SELECT id, occurrence FROM memory_emotional_patterns WHERE user_id=? AND pattern=? AND resolved_at IS NULL",
        [$userId, $pattern]
    );

    if ($existing) {
        db_run(
            "UPDATE memory_emotional_patterns SET occurrence=occurrence+1, intensity=?, last_seen=date('now') WHERE id=?",
            [round($score, 2), $existing['id']]
        );
    } else {
        db_run(
            "INSERT INTO memory_emotional_patterns (user_id, pattern, intensity) VALUES (?, ?, ?)",
            [$userId, $pattern, round($score, 2)]
        );
    }
}

/**
 * Mark an emotional pattern as resolved (e.g. user shows sustained recovery).
 */
function memory_resolve_pattern(int $userId, string $pattern): void {
    db_run(
        "UPDATE memory_emotional_patterns SET resolved_at=date('now') WHERE user_id=? AND pattern=? AND resolved_at IS NULL",
        [$userId, $pattern]
    );

    // Store a recovery episode
    memory_store_episode(
        $userId,
        "Showed signs of recovery from {$pattern}",
        MEMORY_TYPE_RECOVERY,
        ['emotional_score' => 0.2, 'tags' => ['recovery', $pattern]]
    );
}

/**
 * Get all emotional patterns for a user (active and resolved).
 */
function memory_get_emotional_patterns(int $userId): array {
    return db_query(
        "SELECT * FROM memory_emotional_patterns WHERE user_id=? ORDER BY occurrence DESC, last_seen DESC",
        [$userId]
    );
}

// ── SESSION COMPRESSION ───────────────────────────────────────────────────

/**
 * Compress a chat session into episodic memories.
 * Called by cron (api/cron.php) after session ends or next morning.
 * Uses AI to extract key moments worth remembering.
 *
 * @param int    $userId
 * @param string $sessionDate  YYYY-MM-DD
 */
function memory_compress_session(int $userId, string $sessionDate = ''): void {
    if (!$sessionDate) $sessionDate = date('Y-m-d', strtotime('-1 day'));

    $session = db_one(
        "SELECT messages FROM chat_sessions WHERE user_id=? AND session_date=?",
        [$userId, $sessionDate]
    );

    if (!$session) return;

    $messages = json_decode($session['messages'] ?? '[]', true);
    if (count($messages) < 2) return;

    // Build conversation text
    $convoText = '';
    foreach ($messages as $m) {
        $role = ucfirst($m['role'] ?? 'user');
        $convoText .= "{$role}: " . ($m['content'] ?? '') . "\n";
    }

    // Ask AI to extract structured memories
    $extractPrompt = "Analyse this coaching conversation and extract the most important things to remember about this person. Return ONLY a JSON object with these fields:
{
  \"session_summary\": \"1-2 sentence summary of what was discussed\",
  \"breakthroughs\": [\"array of any emotional or insight breakthroughs\"],
  \"triggers\": [\"emotional triggers or recurring stressors mentioned\"],
  \"goals\": [\"goals or intentions the user expressed\"],
  \"emotional_state\": \"overall emotional state during this session\",
  \"themes\": [\"key themes\"]
}";

    // Use route_ai_request_sync if available, else skip AI extraction
    $extraction = null;
    if (function_exists('route_ai_request_sync')) {
        require_once __DIR__ . '/../providers/router.php';
        $raw = route_ai_request_sync(
            [['role' => 'user', 'content' => $convoText]],
            $extractPrompt,
            500,
            ['provider' => 'claude']
        );
        if ($raw) {
            $clean = preg_replace('/```json|```/', '', $raw);
            $extraction = json_decode(trim($clean), true);
        }
    }

    // Store session summary episode
    $summary = $extraction['session_summary']
        ?? _memory_simple_summary($messages);

    $profile = db_one("SELECT * FROM coach_profiles WHERE user_id=?", [$userId]);
    $emotionResult = detect_emotion($convoText);

    memory_store_episode($userId, $summary, MEMORY_TYPE_SESSION, [
        'emotional_score' => $emotionResult['score'],
        'tags'            => $extraction['themes'] ?? [],
        'session_date'    => $sessionDate,
        'generate_embedding' => true,
    ]);

    // Store breakthroughs
    foreach (($extraction['breakthroughs'] ?? []) as $bt) {
        if (strlen(trim($bt)) > 10) {
            memory_store_episode($userId, $bt, MEMORY_TYPE_BREAKTHROUGH, [
                'emotional_score' => 0.6,
                'session_date'    => $sessionDate,
                'tags'            => ['breakthrough'],
            ]);
        }
    }

    // Store triggers
    foreach (($extraction['triggers'] ?? []) as $trigger) {
        if (strlen(trim($trigger)) > 5) {
            memory_store_episode($userId, $trigger, MEMORY_TYPE_TRIGGER, [
                'emotional_score' => 0.7,
                'session_date'    => $sessionDate,
                'tags'            => ['trigger'],
            ]);
        }
    }

    // Store goals
    foreach (($extraction['goals'] ?? []) as $goal) {
        if (strlen(trim($goal)) > 5) {
            memory_store_episode($userId, $goal, MEMORY_TYPE_GOAL, [
                'emotional_score' => 0.4,
                'session_date'    => $sessionDate,
                'tags'            => ['goal'],
            ]);
        }
    }

    // Track emotional patterns
    if ($emotionResult['state'] !== 'neutral') {
        memory_track_emotional_pattern($userId, $emotionResult['state'], $emotionResult['score']);
    }

    // Check for recovery: if user shows sustained recovery, resolve pattern
    $scores = build_emotional_scores($userId);
    if ($scores['recovery_progress'] > 0.75 && $scores['burnout_risk'] < 0.25) {
        memory_resolve_pattern($userId, 'burnout_episode');
    }
}

/**
 * Simple rule-based summary when AI isn't available.
 */
function _memory_simple_summary(array $messages): string {
    $userMessages = array_filter($messages, fn($m) => ($m['role'] ?? '') === 'user');
    $texts = array_map(fn($m) => $m['content'] ?? '', $userMessages);
    $combined = implode(' ', $texts);
    return mb_substr(strip_tags($combined), 0, 200) . (strlen($combined) > 200 ? '…' : '');
}

// ── MEMORY CONTROLS (Phase 7 foundation) ─────────────────────────────────

/**
 * Get all episodes for a user (for the memory control panel).
 */
function memory_get_user_episodes(int $userId, int $page = 1, int $perPage = 20): array {
    $offset = ($page - 1) * $perPage;
    $rows = db_query(
        "SELECT id, type, summary, tags, emotional_score, importance, session_date, created_at
         FROM memory_episodes WHERE user_id=?
         ORDER BY importance DESC, created_at DESC LIMIT ? OFFSET ?",
        [$userId, $perPage, $offset]
    );

    foreach ($rows as &$r) {
        $r['summary'] = _memory_decrypt($userId, $r['summary']);
    }
    return $rows;
}

/**
 * Delete a specific memory episode (user-controlled).
 */
function memory_delete_episode(int $userId, int $episodeId): bool {
    $ep = db_one("SELECT id FROM memory_episodes WHERE id=? AND user_id=?", [$episodeId, $userId]);
    if (!$ep) return false;
    db_run("DELETE FROM memory_episodes WHERE id=?", [$episodeId]);
    return true;
}

/**
 * Export all memories for a user as JSON (GDPR-friendly).
 */
function memory_export(int $userId): array {
    $episodes = db_query(
        "SELECT type, summary, raw_text, tags, emotional_score, session_date, created_at
         FROM memory_episodes WHERE user_id=? ORDER BY created_at DESC",
        [$userId]
    );

    foreach ($episodes as &$ep) {
        $ep['summary']  = _memory_decrypt($userId, $ep['summary']);
        $ep['raw_text'] = $ep['raw_text'] ? _memory_decrypt($userId, $ep['raw_text']) : null;
    }

    return [
        'episodes' => $episodes,
        'emotional_patterns' => memory_get_emotional_patterns($userId),
        'exported_at' => date('c'),
    ];
}

// ── ENCRYPTION HELPERS (Phase 7) ──────────────────────────────────────────

/**
 * Encrypt a string using AES-256-GCM.
 * The key is derived from a site-wide salt + user-specific ID.
 */
function _memory_encrypt(int $userId, string $plaintext): string {
    if (empty($plaintext)) return '';
    $key  = _memory_get_key($userId);
    $iv   = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-gcm'));
    $tag  = '';
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($iv . $tag . $ciphertext);
}

/**
 * Decrypt a string using AES-256-GCM.
 */
function _memory_decrypt(int $userId, string $data): string {
    if (empty($data)) return '';
    // If it's not base64, assume it was stored unencrypted (legacy support)
    $raw = base64_decode($data, true);
    if ($raw === false) return $data;

    $key    = _memory_get_key($userId);
    $ivlen  = openssl_cipher_iv_length('aes-256-gcm');
    $taglen = 16; // GCM default tag length
    $iv     = substr($raw, 0, $ivlen);
    $tag    = substr($raw, $ivlen, $taglen);
    $cipher = substr($raw, $ivlen + $taglen);

    $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return ($plain === false) ? $data : $plain;
}

/**
 * Derive a stable 256-bit key for a user.
 */
function _memory_get_key(int $userId): string {
    $salt = get_setting('encryption_salt');
    if (!$salt) {
        $salt = bin2hex(random_bytes(32));
        set_setting('encryption_salt', $salt);
    }
    return hash('sha256', $salt . $userId, true);
}

/**
 * Evolve the AI Companion's relationship level and personality style (Phase 10).
 */
function memory_evolve_companion(int $userId): void {
    $profile = db_one("SELECT * FROM coach_profiles WHERE user_id=?", [$userId]);
    if (!$profile) return;

    $currentLevel = (int)$profile['relationship_level'];
    $epCount      = db_count('memory_episodes', 'user_id=?', [$userId]);
    $vulnerability = db_one("SELECT AVG(emotional_score) as v FROM memory_episodes WHERE user_id=?", [$userId])['v'] ?? 0;

    $newLevel = $currentLevel;

    // Progression Logic:
    // Level 1: Stranger (0-10 episodes)
    // Level 2: Acquaintance (10-30 episodes)
    // Level 3: Trusted Companion (30-100 episodes + avg vulnerability > 0.4)
    // Level 4: Core Support (100+ episodes + avg vulnerability > 0.6)

    if ($epCount > 100 && $vulnerability > 0.6) $newLevel = 4;
    elseif ($epCount > 30 && $vulnerability > 0.4) $newLevel = 3;
    elseif ($epCount > 10) $newLevel = 2;

    if ($newLevel !== $currentLevel) {
        db_run("UPDATE coach_profiles SET relationship_level=? WHERE user_id=?", [$newLevel, $userId]);
    }

    // Personality Adaptation:
    // If the user's recent moods are consistently high, maybe shift to a more "playful" or "co-creative" style.
    // If low, stay "gentle" or "supportive".
    $recentMood = db_one("SELECT AVG(score) as s FROM mood_logs WHERE user_id=? AND logged_date > date('now','-7 days')", [$userId])['s'] ?? 5;
    
    $newStyle = 'gentle';
    if ($recentMood >= 8) $newStyle = 'playful';
    elseif ($recentMood >= 6) $newStyle = 'reflective';
    elseif ($recentMood < 4) $newStyle = 'stoic'; // Grounded and steady during crisis

    db_run("UPDATE coach_profiles SET personality_style=? WHERE user_id=?", [$newStyle, $userId]);
}
