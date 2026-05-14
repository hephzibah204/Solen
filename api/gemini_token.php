<?php
/**
 * /api/gemini_token.php — Securely deliver Gemini API key to authenticated users
 *
 * Fix: explicit session_start() before is_logged_in() so cookies are available
 * on all environments. Also returns model name so frontend stays in sync.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/db.php';

header('Content-Type: application/json');

// Allow same-site CORS for PWA / fetch calls
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? ''));
header('Access-Control-Allow-Credentials: true');

if (!is_logged_in()) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized — session may have expired']));
}

$user = current_user();
if ($user['plan'] !== 'premium' && $user['role'] !== 'admin') {
    http_response_code(403);
    die(json_encode(['error' => 'Live Voice sessions are a Premium feature. Please upgrade.', 'upgrade_required' => true]));
}

$apiKey = get_api_key('gemini_api_key', 'GEMINI_API_KEY');

if (!$apiKey) {
    http_response_code(500);
    die(json_encode(['error' => 'Gemini API Key not configured on this server']));
}

// Return key + current live model so JS never hard-codes a stale model name
$model = get_setting('gemini_live_model', 'gemini-2.0-flash-live-001');

echo json_encode([
    'key'   => $apiKey,
    'model' => $model,
]);
