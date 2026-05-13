<?php
/**
 * Solen Groq AI Provider
 * API: OpenAI Compatible
 * Base URL: https://api.groq.com/openai/v1
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';

function provider_stream_groq(array $messages, string $system, int $maxTokens, ?string $model = null): void {
    $key = get_api_key('groq_api_key', 'GROQ_API_KEY');
    if (!$key) throw new RuntimeException('Groq API key not configured');

    $model   = $model ?: get_setting('groq_model', 'llama-3.3-70b-versatile');
    $payload = [
        'model'      => $model,
        'messages'   => array_merge(
            $system ? [['role' => 'system', 'content' => $system]] : [],
            $messages
        ),
        'max_tokens' => $maxTokens,
        'stream'     => true,
    ];

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST          => true,
        CURLOPT_POSTFIELDS    => json_encode($payload),
        CURLOPT_HTTPHEADER    => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $key,
        ],
        CURLOPT_WRITEFUNCTION => 'sse_passthrough_generic',
        CURLOPT_TIMEOUT       => 120,
    ]);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (curl_errno($ch)) { $err = curl_error($ch); curl_close($ch); throw new RuntimeException($err); }
    curl_close($ch);
    if ($httpCode >= 400) throw new RuntimeException("Groq API Error: HTTP {$httpCode}");
}

function provider_sync_groq(array $messages, string $system, int $maxTokens, ?string $model = null): ?string {
    $key = get_api_key('groq_api_key', 'GROQ_API_KEY');
    if (!$key) return null;

    $model   = $model ?: get_setting('groq_model', 'llama-3.3-70b-versatile');
    $payload = [
        'model'    => $model,
        'messages' => array_merge(
            $system ? [['role' => 'system', 'content' => $system]] : [],
            $messages
        ),
        'max_tokens' => $maxTokens,
        'stream'     => false,
    ];

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $key,
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
