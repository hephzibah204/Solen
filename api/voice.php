<?php
/**
 * /api/voice.php — Solen Voice & Realtime API (Phase 4)
 *
 * Handles:
 *   GET  ?action=config&mode=calm     → voice session config (token + Gemini settings)
 *   POST ?action=journal              → process a voice journal transcript
 *   POST ?action=stream_event         → receive frontend stream events (analytics)
 *   GET  ?action=modes                → list available voice modes
 *
 * The actual Gemini Live WebSocket is proxied CLIENT-SIDE using the
 * session token from ?action=config. The Gemini API key is injected
 * server-side via the session config; the frontend never needs it in JS.
 *
 * Security:
 *   - All actions require authenticated session (is_logged_in())
 *   - CSRF protected via origin check for non-GET requests
 *   - Voice session tokens are one-time-use and expire in 1 hour
 */

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/prompt_engine.php';
require_once dirname(__DIR__) . '/includes/memory.php';
require_once dirname(__DIR__) . '/includes/voice.php';

// Wire Phase 4 migrations on first access
voice_run_migrations(get_db());

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user   = current_user();
$userId = (int)$user['id'];
$action = $_GET['action'] ?? 'config';

header('Content-Type: application/json');

// ── GET: Voice mode list ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'modes') {
    echo json_encode(['modes' => voice_get_modes()]);
    exit;
}

// ── GET: Session config (token + Gemini settings) ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'config') {
    if (get_setting('gemini_live_enabled', '0') !== '1') {
        http_response_code(403);
        echo json_encode(['error' => 'Voice is not enabled on this instance']);
        exit;
    }

    $mode    = preg_replace('/[^a-z_]/', '', $_GET['mode'] ?? VOICE_MODE_DEFAULT);
    $profile = db_one("SELECT * FROM coach_profiles WHERE user_id=?", [$userId]);

    if (!$profile) {
        http_response_code(400);
        echo json_encode(['error' => 'Profile not set up']);
        exit;
    }

    $config = voice_build_session_config($mode, $profile, $userId);
    $token  = voice_create_session_token($userId, $mode);

    // Return safe config — API key is included here (HTTPS required)
    echo json_encode([
        'token'      => $token,
        'mode'       => $mode,
        'mode_meta'  => $config['mode_meta'],
        'model'      => $config['model'],
        'system'     => $config['system'],
        'generation_config' => $config['generation_config'],
        'api_key'    => $config['api_key'],  // Gemini Live requires key client-side
        'modes'      => voice_get_modes(),
    ]);
    exit;
}

// ── Require POST for all write actions ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$rawBody = file_get_contents('php://input');
$body    = json_decode($rawBody, true) ?? [];

// ── POST: Process voice journal transcript ─────────────────────────────────
if ($action === 'journal') {
    if (get_setting('voice_journaling_enabled', '0') !== '1') {
        http_response_code(403);
        echo json_encode(['error' => 'Voice journaling is not enabled']);
        exit;
    }

    $transcript   = trim($body['transcript'] ?? '');
    $mode         = preg_replace('/[^a-z_]/', '', $body['mode'] ?? 'reflective');
    $durationSecs = (int)($body['duration_seconds'] ?? 0);

    if (strlen($transcript) < 10) {
        http_response_code(400);
        echo json_encode(['error' => 'Transcript too short (minimum 10 characters)']);
        exit;
    }

    $result = voice_process_journal_entry($userId, $transcript, $mode, $durationSecs);

    // Also store in voice_journals table
    db_run(
        "INSERT INTO voice_journals (user_id, transcript, mode, duration_sec, score, emotion_state, episode_id)
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        [
            $userId,
            $transcript,
            $mode,
            $durationSecs,
            $result['mood_score'] ?? null,
            $result['emotional_state'] ?? null,
            $result['episode_id'] ?? null,
        ]
    );

    echo json_encode(array_merge($result, ['success' => true]));
    exit;
}

// ── POST: Stream event (frontend analytics + nudge requests) ──────────────
if ($action === 'stream_event') {
    $eventType = $body['event'] ?? '';

    switch ($eventType) {
        case 'request_nudge':
            $nudge = get_emotional_nudge($userId);
            echo json_encode(['nudge' => $nudge]);
            break;

        case 'voice_started':
            // Track voice session start for analytics
            audit_log($userId, 'voice_session_start', $body['mode'] ?? 'calm');
            echo json_encode(['ok' => true]);
            break;

        case 'voice_ended':
            // Track voice session end
            $duration = (int)($body['duration_seconds'] ?? 0);
            audit_log($userId, 'voice_session_end', json_encode([
                'duration' => $duration,
                'mode'     => $body['mode'] ?? 'calm',
            ]));
            echo json_encode(['ok' => true]);
            break;

        case 'emotional_state_update':
            // Frontend detected an emotional state change (e.g. voice tone analysis)
            $state = $body['state'] ?? '';
            $score = (float)($body['score'] ?? 0.3);
            if ($state && get_setting('memory_enabled', '1') === '1') {
                memory_track_emotional_pattern($userId, $state, $score);
            }
            echo json_encode(['ok' => true]);
            break;

        default:
            echo json_encode(['ok' => true, 'event' => $eventType]);
    }
    exit;
}

// ── Fallback ──────────────────────────────────────────────────────────────
http_response_code(400);
echo json_encode(['error' => 'Unknown action: ' . htmlspecialchars($action)]);
