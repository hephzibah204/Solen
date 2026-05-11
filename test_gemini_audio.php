<?php
$env = parse_ini_file(__DIR__ . '/.env');
$apiKey = $env['GEMINI_API_KEY'] ?? '';
$url = 'https://generativelanguage.googleapis.com/v1alpha/models/gemini-2.0-flash-exp:generateContent?key=' . $apiKey;

$payload = [
    'contents' => [
        ['role' => 'user', 'parts' => [['text' => 'Say hello and how are you?']]]
    ],
    'generationConfig' => [
        'responseModalities' => ['AUDIO'],
        'speechConfig' => [
            'voiceConfig' => [
                'prebuiltVoiceConfig' => ['voiceName' => 'Puck']
            ]
        ]
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
curl_close($ch);

$decoded = json_decode($response, true);
if (isset($decoded['candidates'][0]['content']['parts'])) {
    foreach ($decoded['candidates'][0]['content']['parts'] as $part) {
        if (isset($part['inlineData'])) {
            echo "Audio found: " . $part['inlineData']['mimeType'] . " (length: " . strlen($part['inlineData']['data']) . ")\n";
        }
    }
} else {
    echo "No audio found. Response: \n" . substr($response, 0, 500) . "\n";
}
