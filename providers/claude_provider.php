<?php
/**
 * Solen Claude Provider
 * Standardised stream + sync interface for Anthropic Claude.
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';

function provider_stream_claude(array $messages, string $system, int $maxTokens): void {
    $key = get_setting('claude_api_key') ?: (defined('CLAUDE_API_KEY') ? CLAUDE_API_KEY : '');
    if (!$key) { sse_provider_error('claude', 'API key not configured'); return; }

    $payload = [
        'model'      => get_setting('claude_model', 'claude-sonnet-4-20250514'),
        'max_tokens' => $maxTokens,
        'stream'     => true,
        'messages'   => $messages,
    ];
    if ($system) $payload['system'] = $system;

    _claude_curl($key, $payload, true);
}

function provider_sync_claude(array $messages, string $system, int $maxTokens): ?string {
    $key = get_setting('claude_api_key') ?: (defined('CLAUDE_API_KEY') ? CLAUDE_API_KEY : '');
    if (!$key) return null;

    $payload = [
        'model'      => get_setting('claude_model', 'claude-sonnet-4-20250514'),
        'max_tokens' => $maxTokens,
        'stream'     => false,
        'messages'   => $messages,
    ];
    if ($system) $payload['system'] = $system;

    return _claude_curl($key, $payload, false);
}

function _claude_curl(string $key, array $payload, bool $stream): ?string {
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST       => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . $key,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_TIMEOUT    => 120,
    ]);

    if ($stream) {
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, 'sse_passthrough_generic');
        curl_exec($ch);
        if (curl_errno($ch)) { curl_close($ch); throw new RuntimeException(curl_error($ch)); }
        curl_close($ch);
        return null;
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $raw = curl_exec($ch);
    if (curl_errno($ch)) { curl_close($ch); return null; }
    curl_close($ch);

    $resp = json_decode($raw, true);
    $text = '';
    foreach ($resp['content'] ?? [] as $blk) {
        if ($blk['type'] === 'text') $text .= $blk['text'];
    }
    return $text ?: null;
}

function sse_passthrough_generic($ch, string $data): int {
    echo $data;
    if (ob_get_level()) ob_flush();
    flush();
    return strlen($data);
}

function sse_provider_error(string $provider, string $msg): void {
    error_log("Solen Provider [{$provider}]: {$msg}");
    // Don't echo SSE here — let the router handle fallback
    throw new RuntimeException($msg);
}
