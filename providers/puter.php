<?php
/**
 * Solen Puter AI Provider
 * Based on Puter.js / API docs
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';

function provider_stream_puter(array $messages, string $system, int $maxTokens, ?string $model = null): void {
    $token = get_api_key('puter_auth_token', 'PUTER_AUTH_TOKEN');
    if (!$token) throw new RuntimeException('Puter Auth Token not configured');

    $model   = $model ?: get_setting('puter_model', 'gpt-4o-mini');
    $payload = [
        'model'      => $model,
        'messages'   => array_merge(
            $system ? [['role' => 'system', 'content' => $system]] : [],
            $messages
        ),
        'max_tokens' => $maxTokens,
        'stream'     => true,
    ];

    $ch = curl_init('https://api.puter.com/puterai/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST          => true,
        CURLOPT_POSTFIELDS    => json_encode($payload),
        CURLOPT_HTTPHEADER    => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ],
        // Note: Puter's streaming format might differ slightly from OpenAI, 
        // but often 'stream': true returns SSE chunks.
        CURLOPT_WRITEFUNCTION => function ($ch, $data) {
            echo $data;
            if (ob_get_level()) ob_flush();
            flush();
            return strlen($data);
        },
        CURLOPT_TIMEOUT       => 120,
    ]);
    curl_exec($ch);
    if (curl_errno($ch)) { $err = curl_error($ch); curl_close($ch); throw new RuntimeException($err); }
    curl_close($ch);
}

function provider_sync_puter(array $messages, string $system, int $maxTokens, ?string $model = null): ?string {
    $token = get_api_key('puter_auth_token', 'PUTER_AUTH_TOKEN');
    if (!$token) return null;

    $model   = $model ?: get_setting('puter_model', 'gpt-4o-mini');
    $payload = [
        'model'    => $model,
        'messages' => array_merge(
            $system ? [['role' => 'system', 'content' => $system]] : [],
            $messages
        ),
        'max_tokens' => $maxTokens,
        'stream'     => false,
    ];

    $ch = curl_init('https://api.puter.com/puterai/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    if (!$raw) return null;

    $resp = json_decode($raw, true);
    // Puter (OpenAI-compatible) response format
    return $resp['choices'][0]['message']['content'] ?? null;
}
