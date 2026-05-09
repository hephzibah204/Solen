<?php
/**
 * Solen Gemini Provider
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';

function provider_stream_gemini(array $messages, string $system, int $maxTokens): void {
    $key   = get_setting('gemini_api_key') ?: (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '');
    if (!$key) throw new RuntimeException('Gemini API key not configured');

    $model = get_setting('gemini_model', 'gemini-1.5-flash');
    $url   = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:streamGenerateContent?key={$key}&alt=sse";

    $contents = [];
    foreach ($messages as $m) {
        $role       = $m['role'] === 'assistant' ? 'model' : 'user';
        $contents[] = ['role' => $role, 'parts' => [['text' => $m['content']]]];
    }

    $payload = [
        'contents'         => $contents,
        'generationConfig' => ['maxOutputTokens' => $maxTokens],
    ];
    if ($system) {
        $payload['systemInstruction'] = ['parts' => [['text' => $system]]];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST          => true,
        CURLOPT_POSTFIELDS    => json_encode($payload),
        CURLOPT_HTTPHEADER    => ['Content-Type: application/json'],
        CURLOPT_WRITEFUNCTION => function ($ch, $data) {
            echo $data;
            if (ob_get_level()) ob_flush();
            flush();
            return strlen($data);
        },
        CURLOPT_TIMEOUT => 120,
    ]);
    curl_exec($ch);
    if (curl_errno($ch)) { $err = curl_error($ch); curl_close($ch); throw new RuntimeException($err); }
    curl_close($ch);
}

function provider_sync_gemini(array $messages, string $system, int $maxTokens): ?string {
    $key   = get_setting('gemini_api_key') ?: (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '');
    if (!$key) return null;

    $model = get_setting('gemini_model', 'gemini-1.5-flash');
    $url   = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}";

    $contents = [];
    foreach ($messages as $m) {
        $role       = $m['role'] === 'assistant' ? 'model' : 'user';
        $contents[] = ['role' => $role, 'parts' => [['text' => $m['content']]]];
    }

    $payload = [
        'contents'         => $contents,
        'generationConfig' => ['maxOutputTokens' => $maxTokens],
    ];
    if ($system) {
        $payload['systemInstruction'] = ['parts' => [['text' => $system]]];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    if (!$raw) return null;

    $resp = json_decode($raw, true);
    return $resp['candidates'][0]['content']['parts'][0]['text'] ?? null;
}
