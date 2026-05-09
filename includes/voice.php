<?php
/**
 * Solen Voice & Realtime System — Phase 4
 *
 * Covers:
 *   #1  Gemini Live Voice Integration  — realtime bidirectional voice via WebSockets
 *   #2  Emotional Voice Modes          — calm / encouraging / bedtime / reflective
 *   #3  Voice Journaling               — speak reflections, transcribe, store as episodes
 *   #4  Realtime Streaming UX helpers  — SSE pacing, typing indicators, stream events
 *
 * This file provides the PHP backend layer.
 * The frontend WebSocket bridge is handled in /api/voice.php
 * and the JavaScript layer is injected into app.php via voice_get_js_config().
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/memory.php';
require_once __DIR__ . '/prompt_engine.php';

// ── VOICE MODE DEFINITIONS ─────────────────────────────────────────────────

/**
 * Emotional voice modes — each modifies the system prompt and Gemini
 * voice settings for a distinct emotional experience.
 */
const VOICE_MODES = [
    'calm' => [
        'label'       => 'Calm',
        'emoji'       => '🌿',
        'description' => 'Slow, grounded, centred. For when you need steadiness.',
        'prompt_mod'  => "Speak slowly and gently. Your voice is calm and unhurried. Use pauses naturally. This person needs steadiness right now — be their anchor.",
        'gemini_voice'=> 'Charon',      // Gemini Live voice name (deep, calm)
        'speaking_rate'=> 0.85,
        'pitch'       => -1.0,
    ],
    'encouraging' => [
        'label'       => 'Encouraging',
        'emoji'       => '✨',
        'description' => 'Warm and energising. For when you need a boost.',
        'prompt_mod'  => "You are warm, uplifting, and energising — but never fake. Celebrate real progress. Bring genuine enthusiasm to the conversation.",
        'gemini_voice'=> 'Aoede',       // Gemini Live voice name (warm, bright)
        'speaking_rate'=> 1.0,
        'pitch'       => 0.5,
    ],
    'bedtime' => [
        'label'       => 'Bedtime',
        'emoji'       => '🌙',
        'description' => 'Soft and winding down. For sleep-time reflections.',
        'prompt_mod'  => "Keep everything soft, brief, and winding down. Help the person decompress and release the day. Never ask stimulating questions. Close sessions gently.",
        'gemini_voice'=> 'Kore',        // Gemini Live voice name (soft, soothing)
        'speaking_rate'=> 0.78,
        'pitch'       => -1.5,
    ],
    'reflective' => [
        'label'       => 'Reflective',
        'emoji'       => '💭',
        'description' => 'Thoughtful and spacious. For deep inner work.',
        'prompt_mod'  => "You are a wise, unhurried presence. Allow space for reflection. Ask one deep question at a time. Let silence breathe. Never rush toward solutions.",
        'gemini_voice'=> 'Fenrir',      // Gemini Live voice name (measured, resonant)
        'speaking_rate'=> 0.9,
        'pitch'       => -0.5,
    ],
];

const VOICE_MODE_DEFAULT = 'calm';

// ── GEMINI LIVE CONFIGURATION ─────────────────────────────────────────────

/**
 * Build the Gemini Live session config for the given voice mode.
 * Passed to the frontend to initialise the WebSocket session.
 *
 * @param string $mode        One of VOICE_MODES keys
 * @param array  $profile     coach_profiles row
 * @param int    $userId      For memory injection
 * @return array              Config for the JS Gemini Live client
 */
function voice_build_session_config(string $mode, array $profile, int $userId): array {
    $modeConfig = VOICE_MODES[$mode] ?? VOICE_MODES[VOICE_MODE_DEFAULT];

    // Build a voice-aware system prompt
    $systemPrompt = build_system_prompt(
        $profile,
        [],
        '',
        ['user_id' => $userId, 'voice_mode' => $mode]
    );
    $systemPrompt .= "\n\n" . $modeConfig['prompt_mod'];
    $systemPrompt .= "\n\nYou are speaking — not writing. Keep responses SHORT (1–3 sentences). No lists, no markdown. Sound natural and conversational. Use the person's name occasionally for warmth.";

    $geminiApiKey = get_setting('gemini_api_key') ?: (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '');
    $model        = get_setting('gemini_live_model', 'gemini-2.0-flash-live-001');

    return [
        'api_key'    => $geminiApiKey,   // sent to frontend — ensure HTTPS always
        'model'      => $model,
        'voice_mode' => $mode,
        'voice_name' => $modeConfig['gemini_voice'],
        'system'     => $systemPrompt,
        'generation_config' => [
            'response_modalities' => ['AUDIO'],
            'speech_config' => [
                'voice_config' => [
                    'prebuilt_voice_config' => [
                        'voice_name' => $modeConfig['gemini_voice'],
                    ],
                ],
            ],
        ],
        'mode_meta' => [
            'label'        => $modeConfig['label'],
            'emoji'        => $modeConfig['emoji'],
            'description'  => $modeConfig['description'],
            'speaking_rate'=> $modeConfig['speaking_rate'],
            'pitch'        => $modeConfig['pitch'],
        ],
    ];
}

/**
 * Get all voice modes as an array suitable for rendering the mode picker.
 */
function voice_get_modes(): array {
    $modes = [];
    foreach (VOICE_MODES as $key => $cfg) {
        $modes[] = [
            'key'         => $key,
            'label'       => $cfg['label'],
            'emoji'       => $cfg['emoji'],
            'description' => $cfg['description'],
        ];
    }
    return $modes;
}

// ── VOICE JOURNALING ──────────────────────────────────────────────────────

/**
 * Process a completed voice journal entry.
 *
 * Takes the transcribed text from the frontend (STT done client-side via
 * Gemini or Web Speech API), runs emotion detection, and stores it as a
 * memory episode + mood log entry.
 *
 * @param int    $userId
 * @param string $transcript    Raw transcribed text
 * @param string $mode          Voice mode used (for context)
 * @param int    $durationSecs  Recording duration
 * @return array  {episode_id, mood_score, emotional_state, nudge}
 */
function voice_process_journal_entry(int $userId, string $transcript, string $mode = 'reflective', int $durationSecs = 0): array {
    if (strlen(trim($transcript)) < 10) {
        return ['error' => 'Transcript too short'];
    }

    // Emotion detection
    $emotion = detect_emotion($transcript);
    $emotionalState = $emotion['state'];
    $emotionalScore = $emotion['score'];

    // Map emotion score to mood score (1–5 scale)
    $moodScore = match(true) {
        $emotionalScore >= 0.9 => 1,
        $emotionalScore >= 0.7 => 2,
        $emotionalScore >= 0.5 => 3,
        $emotionalScore >= 0.2 => 4,
        default                => 5,
    };

    // Log mood
    db_run(
        "INSERT INTO mood_logs (user_id, score, label, emoji, notes) VALUES (?, ?, ?, ?, ?)",
        [
            $userId,
            $moodScore,
            $emotionalState,
            _voice_mood_emoji($moodScore),
            mb_substr($transcript, 0, 500),
        ]
    );

    // Store as memory episode
    $episodeId = memory_store_episode(
        $userId,
        _voice_summarise_journal($transcript),
        MEMORY_TYPE_LIFE_MOMENT,
        [
            'emotional_score'    => $emotionalScore,
            'tags'               => ['voice_journal', $mode],
            'raw_text'           => $transcript,
            'generate_embedding' => true,
        ]
    );

    // Track emotional pattern
    memory_track_emotional_pattern($userId, $emotionalState, $emotionalScore);

    // Get a nudge if applicable
    $nudge = get_emotional_nudge($userId);

    return [
        'episode_id'     => $episodeId,
        'mood_score'     => $moodScore,
        'emotional_state'=> $emotionalState,
        'nudge'          => $nudge,
        'duration_secs'  => $durationSecs,
    ];
}

/**
 * Generate a 1-sentence journal summary (without AI, using first meaningful sentence).
 */
function _voice_summarise_journal(string $transcript): string {
    // Split into sentences
    $sentences = preg_split('/(?<=[.!?])\s+/', trim($transcript), -1, PREG_SPLIT_NO_EMPTY);
    if (empty($sentences)) return mb_substr($transcript, 0, 150);

    // Return first substantive sentence (>20 chars)
    foreach ($sentences as $s) {
        if (strlen($s) > 20) return mb_substr($s, 0, 200);
    }

    return mb_substr($transcript, 0, 150);
}

function _voice_mood_emoji(int $score): string {
    return match($score) {
        1 => '😰', 2 => '😔', 3 => '😐', 4 => '🙂', 5 => '😊',
        default => '😐',
    };
}

// ── REALTIME STREAMING UX HELPERS ─────────────────────────────────────────

/**
 * Emit a typed SSE event with structured payload.
 * Used by the streaming endpoints to give the frontend rich events
 * for typing indicators, emotional pacing, voice feedback, etc.
 *
 * @param string $type     Event type (see STREAM_EVENTS below)
 * @param array  $payload  Event data
 */
function stream_emit(string $type, array $payload = []): void {
    $data = json_encode(array_merge(['type' => $type], $payload));
    echo "data: {$data}\n\n";
    if (ob_get_level()) ob_flush();
    flush();
}

/**
 * Emit a stream_start event — signals the frontend to show typing indicator.
 */
function stream_start(string $provider = '', string $emotionalState = ''): void {
    stream_emit('stream_start', [
        'provider'        => $provider,
        'emotional_state' => $emotionalState,
        'timestamp'       => microtime(true),
    ]);
}

/**
 * Emit a stream_end event with metadata for analytics.
 */
function stream_end(float $startTime, int $tokenEstimate = 0, string $provider = ''): void {
    stream_emit('stream_end', [
        'duration_ms'    => round((microtime(true) - $startTime) * 1000),
        'token_estimate' => $tokenEstimate,
        'provider'       => $provider,
    ]);
}

/**
 * Emit a nudge event — frontend can display as a soft proactive message.
 */
function stream_nudge(int $userId): void {
    if (get_setting('nudge_enabled', '1') !== '1') return;
    $nudge = get_emotional_nudge($userId);
    if ($nudge) {
        stream_emit('nudge', ['message' => $nudge]);
    }
}

/**
 * Emit an emotional pacing hint — frontend uses this to adjust
 * typing animation speed and response density.
 */
function stream_emotional_pace(string $emotionalState): void {
    $pacing = match($emotionalState) {
        'crisis'        => ['speed' => 'very_slow', 'pause_ms' => 600],
        'high_distress' => ['speed' => 'slow',      'pause_ms' => 400],
        'burnout'       => ['speed' => 'slow',      'pause_ms' => 350],
        'anxiety_high'  => ['speed' => 'slow',      'pause_ms' => 300],
        'low'           => ['speed' => 'medium',    'pause_ms' => 200],
        'positive'      => ['speed' => 'normal',    'pause_ms' => 80],
        default         => ['speed' => 'normal',    'pause_ms' => 100],
    };
    stream_emit('emotional_pace', $pacing);
}

// ── VOICE API SESSION TOKEN ───────────────────────────────────────────────

/**
 * Create a short-lived voice session token for the frontend WebSocket
 * to authenticate with /api/voice.php without exposing the Gemini API key
 * in JavaScript (the token is exchanged server-side).
 *
 * Token is stored in voice_sessions table (added below).
 *
 * @param int    $userId
 * @param string $mode
 * @return string  Opaque token (64 hex chars)
 */
function voice_create_session_token(int $userId, string $mode = 'calm'): string {
    $token     = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

    db_run(
        "INSERT INTO voice_sessions (user_id, token_hash, mode, expires_at) VALUES (?, ?, ?, ?)",
        [$userId, $tokenHash, $mode, $expiresAt]
    );

    return $token;
}

/**
 * Validate and consume a voice session token.
 * Returns the session row or null if invalid/expired.
 */
function voice_validate_session_token(string $token): ?array {
    $hash = hash('sha256', $token);
    $session = db_one(
        "SELECT * FROM voice_sessions WHERE token_hash=? AND expires_at > datetime('now') AND used_at IS NULL",
        [$hash]
    );
    if (!$session) return null;

    // Mark as used (one-time token exchange; WebSocket stays open independently)
    db_run(
        "UPDATE voice_sessions SET used_at=datetime('now') WHERE id=?",
        [(int)$session['id']]
    );

    return $session;
}

// ── VOICE SCHEMA MIGRATIONS ───────────────────────────────────────────────

/**
 * Run Phase 4 database migrations.
 * Called from memory_run_migrations() or directly from db.php.
 */
function voice_run_migrations(PDO $pdo): void {
    // Voice session tokens (for WebSocket auth)
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS voice_sessions (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        token_hash  TEXT NOT NULL,
        mode        TEXT NOT NULL DEFAULT 'calm',
        expires_at  TEXT NOT NULL,
        used_at     TEXT,
        created_at  TEXT DEFAULT (datetime('now'))
    );
    CREATE INDEX IF NOT EXISTS idx_voice_sessions_user ON voice_sessions(user_id);
    CREATE INDEX IF NOT EXISTS idx_voice_sessions_token ON voice_sessions(token_hash);
    ");

    // Voice journal entries (transcripts + metadata)
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS voice_journals (
        id           INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        transcript   TEXT NOT NULL,
        summary      TEXT,
        mode         TEXT DEFAULT 'reflective',
        duration_sec INTEGER DEFAULT 0,
        score        INTEGER,
        emotion_state TEXT,
        episode_id   INTEGER REFERENCES memory_episodes(id) ON DELETE SET NULL,
        created_at   TEXT DEFAULT (datetime('now'))
    );
    CREATE INDEX IF NOT EXISTS idx_voice_journals_user ON voice_journals(user_id, created_at);
    ");

    // Migration: Rename mood_score to score if needed
    try {
        $cols = $pdo->query("PRAGMA table_info(voice_journals)")->fetchAll(PDO::FETCH_ASSOC);
        $hasMoodScore = false;
        foreach ($cols as $c) if ($c['name'] === 'mood_score') $hasMoodScore = true;
        if ($hasMoodScore) {
            $pdo->exec("ALTER TABLE voice_journals RENAME COLUMN mood_score TO score");
        }
    } catch (Exception $e) { /* ignore if already renamed or table missing */ }

    // New settings for Phase 4
    $settings = [
        ['gemini_live_model',    'gemini-2.0-flash-live-001'],
        ['voice_default_mode',   'calm'],
        ['voice_journaling_enabled', '0'],
        ['gemini_live_enabled',  '0'],
        ['stream_typing_speed',  'auto'],  // auto|slow|normal|fast
    ];
    $ins = $pdo->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)");
    foreach ($settings as [$k, $v]) $ins->execute([$k, $v]);
}

// ── JS CONFIG BUILDER ─────────────────────────────────────────────────────

/**
 * Build the JavaScript configuration object injected into app.php.
 * Keeps sensitive keys server-side; only passes safe config to the frontend.
 *
 * @param int    $userId
 * @param string $mode
 * @param bool   $voiceEnabled
 * @return string  JSON-encoded config (safe to embed in <script>)
 */
function voice_get_js_config(int $userId, string $mode = 'calm', bool $voiceEnabled = false): string {
    $cfg = [
        'voice_enabled'    => $voiceEnabled && get_setting('gemini_live_enabled', '0') === '1',
        'journaling_enabled' => get_setting('voice_journaling_enabled', '0') === '1',
        'modes'            => voice_get_modes(),
        'default_mode'     => get_setting('voice_default_mode', 'calm'),
        'stream_typing_speed' => get_setting('stream_typing_speed', 'auto'),
        'emotional_pacing' => true,
        'nudges_enabled'   => get_setting('nudge_enabled', '1') === '1',
        'memory_enabled'   => get_setting('memory_enabled', '1') === '1',
    ];

    // Attach a voice session token if voice is enabled
    if ($cfg['voice_enabled']) {
        $cfg['voice_token'] = voice_create_session_token($userId, $mode);
        $cfg['voice_ws_url'] = '/api/voice.php'; // WebSocket endpoint
    }

    return json_encode($cfg, JSON_UNESCAPED_SLASHES);
}
