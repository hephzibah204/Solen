<?php
/**
 * Solen HuggingFace Provider
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';

function provider_stream_huggingface(array $messages, string $system, int $maxTokens): void {
    $key   = get_setting('huggingface_api_key') ?: (defined('HUGGINGFACE_API_KEY') ? HUGGINGFACE_API_KEY : '');
    if (!$key) throw new RuntimeException('HuggingFace API key not configured');

    $model   = get_setting('huggingface_model', 'mistralai/Mistral-7B-Instruct-v0.3');
    $url     = "https://api-inference.huggingface.co/models/{$model}/v1/chat/completions";
    $payload = [
        'model'      => $model,
        'messages'   => array_merge(
            $system ? [['role' => 'system', 'content' => $system]] : [],
            $messages
        ),
        'max_tokens' => $maxTokens,
        'stream'     => true,
    ];

    $ch = curl_init($url);
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
    if (curl_errno($ch)) { $err = curl_error($ch); curl_close($ch); throw new RuntimeException($err); }
    curl_close($ch);
}

function provider_sync_huggingface(array $messages, string $system, int $maxTokens): ?string {
    $key   = get_setting('huggingface_api_key') ?: (defined('HUGGINGFACE_API_KEY') ? HUGGINGFACE_API_KEY : '');
    if (!$key) return null;

    $model   = get_setting('huggingface_model', 'mistralai/Mistral-7B-Instruct-v0.3');
    $url     = "https://api-inference.huggingface.co/models/{$model}/v1/chat/completions";
    $payload = [
        'model'    => $model,
        'messages' => array_merge(
            $system ? [['role' => 'system', 'content' => $system]] : [],
            $messages
        ),
        'max_tokens' => $maxTokens,
        'stream'     => false,
    ];

    $ch = curl_init($url);
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
