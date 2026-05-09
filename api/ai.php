<?php
/**
 * /api/ai.php — Solen AI Streaming Proxy (Phase 1 upgrade)
 *
 * Previously: raw provider switch-case inline.
 * Now: delegates to route_ai_request() for provider selection,
 *       fallback handling, cost optimisation, and emotional routing.
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/prompt_engine.php';
require_once dirname(__DIR__) . '/includes/memory.php';
require_once dirname(__DIR__) . '/includes/predictive.php'; // Phase 10
require_once dirname(__DIR__) . '/providers/router.php';

check_maintenance(true);

if (!is_logged_in())                                     { http_response_code(401); die('Unauthorized'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')               { http_response_code(405); die('Method Not Allowed'); }

// Support the claude.php shim which pre-reads and caches php://input.
$rawBody = $GLOBALS['_SOLEN_CACHED_INPUT'] ?? file_get_contents('php://input');
$body    = json_decode($rawBody, true);
if (!$body) { http_response_code(400); die('Bad request'); }
unset($GLOBALS['_SOLEN_CACHED_INPUT']);

// ── RATE LIMITING & TRIAL ENFORCEMENT (Phase 8) ───────────────────────────
$_rl_user = current_user();
$_rl_uid  = (int)$_rl_user['id'];
$_rl_plan = $_rl_user['plan'] ?? 'free';
$_rl_today= gmdate('Y-m-d');

// 1. Strict Trial Check
if (!user_has_access($_rl_user)) {
    http_response_code(402);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'trial_expired',
        'message' => 'Your 7-day free trial has expired. Please upgrade to continue.',
        'upgrade_url' => '/pricing.php'
    ]);
    exit;
}

// 2. Tiered Rate Limiting
if ($_rl_user['role'] !== 'admin') {
    // Map plans to daily limits
    $planLimits = [
        'free'    => (int)get_setting('ai_daily_limit_free', '20'),
        'plus'    => (int)get_setting('ai_daily_limit_plus', '50'),
        'pro'     => (int)get_setting('ai_daily_limit_pro',  '200'),
        'premium' => (int)get_setting('ai_daily_limit_premium', '500'),
    ];
    $limit = $planLimits[$_rl_plan] ?? 20;

    get_db()->prepare(
        "INSERT INTO ai_rate_limits (user_id, limit_date, request_count)
         VALUES (?, ?, 1)
         ON CONFLICT(user_id, limit_date)
         DO UPDATE SET request_count = request_count + 1"
    )->execute([$_rl_uid, $_rl_today]);

    $row   = db_one("SELECT request_count FROM ai_rate_limits WHERE user_id=? AND limit_date=?", [$_rl_uid, $_rl_today]);
    $count = (int)($row['request_count'] ?? 1);

    if ($count > $limit) {
        http_response_code(429);
        header('Content-Type: application/json');
        header('X-RateLimit-Limit: '   . $limit);
        header('X-RateLimit-Used: '    . $count);
        header('X-RateLimit-Reset: '   . strtotime('tomorrow 00:00:00 UTC'));
        echo json_encode([
            'error'       => 'rate_limit_exceeded',
            'message'     => "You've reached your daily limit of {$limit} requests for the " . ucfirst($_rl_plan) . " plan.",
            'limit'       => $limit,
            'used'        => $count,
            'plan'        => $_rl_plan,
            'upgrade_url' => '/pricing.php',
        ]);
        exit;
    }
    header('X-RateLimit-Limit: '     . $limit);
    header('X-RateLimit-Remaining: ' . max(0, $limit - $count));
    header('X-RateLimit-Reset: '     . strtotime('tomorrow 00:00:00 UTC'));
}

// ── EMOTION DETECTION (Phase 2) ───────────────────────────────────────────
$messages  = $body['messages']  ?? [];
$system    = $body['system']    ?? '';
$maxTokens = (int)($body['max_tokens'] ?? 1000);

$emotionalState = '';
$currentUserText = '';
foreach (array_reverse($messages) as $msg) {
    if ($msg['role'] === 'user') {
        $currentUserText = $msg['content'] ?? '';
        $emotionResult  = detect_emotion($currentUserText);
        $emotionalState = $emotionResult['state'];
        if ($emotionalState === 'crisis') {
            error_log("Solen CRISIS: user_id={$_rl_uid}, indicators=" . implode(',', $emotionResult['indicators']));
        }
        // Phase 3 — track emotional pattern in memory
        if (get_setting('memory_enabled', '1') === '1') {
            memory_track_emotional_pattern($_rl_uid, $emotionalState, $emotionResult['score']);
        }
        break;
    }
}

// ── SSE HEADERS ───────────────────────────────────────────────────────────
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

// ── ROUTE TO AI PROVIDER ──────────────────────────────────────────────────
$provider = strtolower($body['provider'] ?? get_setting('ai_provider', 'claude'));

// Phase 10 — Rebuild system prompt on server to include Relationship & Predictive insights
$systemPrompt = build_system_prompt($profile, [], $emotionalState, [
    'user_id' => $_rl_uid,
    'current_text' => $currentUserText,
    'predictive_insight' => $predInsight
]);

route_ai_request($messages, $systemPrompt, $maxTokens, [
    'provider'        => $provider,
    'emotional_state' => $emotionalState,
    'user_plan'       => $_rl_plan,
    'model'           => $body['model'] ?? null,
    'fallback'        => true,
    'user_id'         => $_rl_uid,       // Phase 3 — memory context
    'current_text'    => $currentUserText, // Phase 3 — semantic search
]);
