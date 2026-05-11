<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../config.php';

$key = getenv('GEMINI_API_KEY');
$model = 'gemini-1.5-flash';
$url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}";

$payload = [
    'contents' => [['parts' => [['text' => 'Say hello in one word.']]]]
];

echo "Testing NEW Gemini Key...\n";
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
]);
$raw = curl_exec($ch);
$info = curl_getinfo($ch);
curl_close($ch);

echo "HTTP Code: " . $info['http_code'] . "\n";
echo "Response: " . $raw . "\n";
