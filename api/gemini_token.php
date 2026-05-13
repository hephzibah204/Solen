<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/db.php';

// Only logged in users can get the token
if (!is_logged_in()) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

$apiKey = get_api_key('gemini_api_key', 'GEMINI_API_KEY');

if (!$apiKey) {
    http_response_code(500);
    die(json_encode(['error' => 'Gemini API Key not configured']));
}

header('Content-Type: application/json');
// We pass the key securely over the authenticated session to the client
echo json_encode(['key' => $apiKey]);
