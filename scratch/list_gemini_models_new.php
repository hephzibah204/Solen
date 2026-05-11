<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../config.php';

$key = getenv('GEMINI_API_KEY');
$url = "https://generativelanguage.googleapis.com/v1/models?key={$key}";

echo "Listing Gemini Models with NEW KEY...\n";
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
]);
$raw = curl_exec($ch);
$info = curl_getinfo($ch);
curl_close($ch);

echo "HTTP Code: " . $info['http_code'] . "\n";
echo "Response: " . $raw . "\n";
