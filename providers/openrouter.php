<?php
/**
 * Solen OpenRouter Provider
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';

function provider_stream_openrouter(array $messages, string $system, int $maxTokens, ?string $model = null): void {
    $key = get_api_key('openrouter_api_key', 'OPENROUTER_API_KEY');
    if (!$key) throw new RuntimeException('OpenRouter API key not configured');

    $model   = $model ?: get_setting('openrouter_model', 'meta-llama/llama-3.3-70b-instruct');
    $payload = [
        'model'      => $model,
        'messages'   => array_merge(
            $system ? [['role' => 'system', 'content' => $system]] : [],
            $messages
        ),
        'max_tokens' => $maxTokens,
        'stream'     => true,
    ];

    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST          => true,
        CURLOPT_POSTFIELDS    => json_encode($payload),
        CURLOPT_HTTPHEADER    => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $key,
            'HTTP-Referer: ' . SITE_URL,
            'X-Title: Solen Wellness Coach',
        ],
        CURLOPT_WRITEFUNCTION => 'sse_passthrough_generic',
        CURLOPT_TIMEOUT       => 120,
    ]);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (curl_errno($ch)) { $err = curl_error($ch); curl_close($ch); throw new RuntimeException($err); }
    curl_close($ch);
    if ($httpCode >= 400) throw new RuntimeException("OpenRouter API Error: HTTP {$httpCode}");
}

function provider_sync_openrouter(array $messages, string $system, int $maxTokens, ?string $model = null): ?string {
    $key = get_api_key('openrouter_api_key', 'OPENROUTER_API_KEY');
    if (!$key) return null;

    $model   = $model ?: get_setting('openrouter_model', 'meta-llama/llama-3.3-70b-instruct');
    $payload = [
        'model'    => $model,
        'messages' => array_merge(
            $system ? [['role' => 'system', 'content' => $system]] : [],
            $messages
        ),
        'max_tokens' => $maxTokens,
        'stream'     => false,
    ];

    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $key,
            'HTTP-Referer: ' . SITE_URL,
            'X-Title: Solen Wellness Coach',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    if (!$raw) return null;

    $resp = json_decode($raw, true);
    return $resp['choices'][0]['message']['content'] ?? null;
}
