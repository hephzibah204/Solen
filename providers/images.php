<?php
/**
 * providers/images.php — AI Image Generation Provider
 *
 * Supports DALL-E 3 (OpenAI) or fallbacks.
 */

require_once __DIR__ . '/../includes/db.php';

function generate_ai_image(string $prompt, string $size = '1024x1024'): ?string {
    $apiKey = get_setting('openai_api_key');
    if (!$apiKey) return null;

    $url = "https://api.openai.com/v1/images/generations";
    $data = [
        "model" => "dall-e-3",
        "prompt" => $prompt,
        "n" => 1,
        "size" => $size
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer $apiKey"
    ]);

    $res = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    if ($info['http_code'] !== 200) {
        error_log("DALL-E Error: " . $res);
        return null;
    }

    $json = json_decode($res, true);
    return $json['data'][0]['url'] ?? null;
}
