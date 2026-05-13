<?php
/**
 * Solen AI Router — Phase 1 Core
 * route_ai_request() selects the best provider, handles fallback,
 * streaming, cost optimisation, emotional routing, and timeout recovery.
 *
 * Usage (streaming):
 *   route_ai_request($messages, $system, $maxTokens, $opts);
 *
 * Usage (non-streaming, returns string):
 *   $text = route_ai_request_sync($messages, $system, $maxTokens, $opts);
 */

require_once dirname(__DIR__) . '/providers/gemini.php';
require_once dirname(__DIR__) . '/providers/openrouter.php';
require_once dirname(__DIR__) . '/providers/huggingface.php';
require_once dirname(__DIR__) . '/providers/puter.php';
require_once dirname(__DIR__) . '/providers/groq.php';
require_once dirname(__DIR__) . '/providers/hypereal.php';
require_once dirname(__DIR__) . '/providers/fireworks.php';

/**
 * Core router — streams directly to the client via SSE.
 *
 * $opts keys:
 *   provider        string|null  force a specific provider (skips selection)
 *   emotional_state string|null  'crisis'|'high_distress'|'low'|null
 *   user_plan       string|null  'free'|'pro'|'premium'
 *   model           string|null  override model for openrouter
 *   fallback        bool         whether to attempt fallback (default true)
 */
function route_ai_request(array $messages, string $system, int $maxTokens = 1000, array $opts = []): void {
    $provider = $opts['provider'] ?? null;
    $fallback  = $opts['fallback'] ?? true;

    // Emotional routing: crisis situations always use the most reliable available provider
    if (($opts['emotional_state'] ?? '') === 'crisis') {
        $provider = null; // Force smart auto-selection for crisis
        $fallback  = true;
    }

    // Smart routing: 'auto', 'claude', or empty => auto-select best available
    if (!$provider || $provider === 'auto' || $provider === 'claude') {
        $provider = _select_provider($opts['user_plan'] ?? 'free');
        error_log("Solen SmartRouter: auto-selected provider={$provider}");
    }

    $providers_tried = [];
    $attempt_order   = _build_attempt_order($provider, $fallback);

    foreach ($attempt_order as $p) {
        $providers_tried[] = $p;
        $ok = _stream_provider($p, $messages, $system, $maxTokens, $opts);
        if ($ok) return;
        // Provider failed — log and try next
        error_log("Solen Router: {$p} failed, trying next provider");
    }

    // All providers exhausted
    sse_router_error("All AI providers failed after trying: " . implode(', ', $providers_tried));
}

/**
 * Non-streaming sync call — returns the full text response or null.
 * Used for memory compression, emotion detection, background tasks.
 */
function route_ai_request_sync(array $messages, string $system, int $maxTokens = 500, array $opts = []): ?string {
    $provider = $opts['provider'] ?? _select_provider($opts['user_plan'] ?? 'free');
    $attempt_order = _build_attempt_order($provider, true);

    foreach ($attempt_order as $p) {
        $text = _sync_provider($p, $messages, $system, $maxTokens, $opts);
        if ($text !== null) return $text;
        error_log("Solen Router (sync): {$p} failed, trying next");
    }
    return null;
}

// ── PROVIDER SELECTION ────────────────────────────────────────────────────

/**
 * Select the best available provider based on:
 * - Admin setting (primary)
 * - Cost optimisation (free users → cheaper models)
 * - Key availability
 */
function _select_provider(string $userPlan): string {
    // Admin-configured primary provider (ignore 'claude' and 'auto' — they're removed/virtual)
    $configured = strtolower(get_setting('ai_provider', 'gemini'));
    if ($configured === 'claude' || $configured === 'auto') {
        $configured = ''; // Will fall through to auto-selection below
    }

    // For free users, prefer cheaper providers to reduce cost
    if ($userPlan === 'free') {
        $cheap = get_setting('ai_provider_free', ''); // admin can override
        if ($cheap && $cheap !== 'claude' && _provider_has_key($cheap)) return $cheap;
        // Default cost-saving order for free: Groq → Gemini → Fireworks → HuggingFace → OpenRouter → Hypereal → Puter
        foreach (['groq', 'gemini', 'fireworks', 'huggingface', 'openrouter', 'hypereal', 'puter'] as $p) {
            if (_provider_has_key($p)) return $p;
        }
    }

    // Paid users: use configured provider if it has a key
    if ($configured && _provider_has_key($configured)) return $configured;

    // Smart fallback: try providers in reliability/quality order
    foreach (['gemini', 'openrouter', 'groq', 'fireworks', 'hypereal', 'huggingface', 'puter'] as $p) {
        if (_provider_has_key($p)) return $p;
    }

    return 'openrouter'; // last resort — will error gracefully if no key
}

function _provider_has_key(string $provider): bool {
    return match ($provider) {
        'gemini'      => !empty(get_api_key('gemini_api_key', 'GEMINI_API_KEY')),
        'openrouter'  => !empty(get_api_key('openrouter_api_key', 'OPENROUTER_API_KEY')),
        'huggingface' => !empty(get_api_key('huggingface_api_key', 'HUGGINGFACE_API_KEY')),
        'groq'        => !empty(get_api_key('groq_api_key', 'GROQ_API_KEY')),
        'hypereal'    => !empty(get_api_key('hypereal_api_key', 'HYPEREAL_API_KEY')),
        'fireworks'   => !empty(get_api_key('fireworks_api_key', 'FIREWORKS_API_KEY')),
        'puter'       => !empty(get_api_key('puter_auth_token', 'PUTER_AUTH_TOKEN')),
        default       => false,
    };
}

/**
 * Build the attempt order: primary first, then sensible fallbacks.
 */
function _build_attempt_order(string $primary, bool $fallback): array {
    if (!$fallback) return [$primary];

    // Reliability order (no claude — it's been removed)
    $all = ['gemini', 'openrouter', 'groq', 'fireworks', 'hypereal', 'huggingface', 'puter'];
    // Primary first, then the rest (only include providers with keys if possible)
    $ordered = array_merge([$primary], array_values(array_diff($all, [$primary])));
    // Prefer providers that actually have keys (for faster fallback)
    $withKey    = array_filter($ordered, '_provider_has_key');
    $withoutKey = array_filter($ordered, fn($p) => !_provider_has_key($p));
    return array_values(array_merge($withKey, $withoutKey));
}

// ── STREAMING DISPATCH ────────────────────────────────────────────────────

function _stream_provider(string $provider, array $messages, string $system, int $maxTokens, array $opts): bool {
    try {
        match ($provider) {
            'gemini'      => provider_stream_gemini($messages, $system, $maxTokens),
            'openrouter'  => provider_stream_openrouter($messages, $system, $maxTokens, $opts['model'] ?? null),
            'huggingface' => provider_stream_huggingface($messages, $system, $maxTokens),
            'groq'        => provider_stream_groq($messages, $system, $maxTokens, $opts['model'] ?? null),
            'hypereal'    => provider_stream_hypereal($messages, $system, $maxTokens, $opts['model'] ?? null),
            'fireworks'   => provider_stream_fireworks($messages, $system, $maxTokens, $opts['model'] ?? null),
            'puter'       => provider_stream_puter($messages, $system, $maxTokens, $opts['model'] ?? null),
            // 'claude' has been removed — default to gemini or openrouter
            default       => provider_stream_gemini($messages, $system, $maxTokens),
        };
        error_log("Solen Router: stream success [{$provider}]");
        return true;
    } catch (Throwable $e) {
        error_log("Solen Router stream error [{$provider}]: " . $e->getMessage());
        return false;
    }
}

function _sync_provider(string $provider, array $messages, string $system, int $maxTokens, array $opts): ?string {
    try {
        return match ($provider) {
            'gemini'      => provider_sync_gemini($messages, $system, $maxTokens),
            'openrouter'  => provider_sync_openrouter($messages, $system, $maxTokens, $opts['model'] ?? null),
            'huggingface' => provider_sync_huggingface($messages, $system, $maxTokens),
            'groq'        => provider_sync_groq($messages, $system, $maxTokens, $opts['model'] ?? null),
            'hypereal'    => provider_sync_hypereal($messages, $system, $maxTokens, $opts['model'] ?? null),
            'fireworks'   => provider_sync_fireworks($messages, $system, $maxTokens, $opts['model'] ?? null),
            'puter'       => provider_sync_puter($messages, $system, $maxTokens, $opts['model'] ?? null),
            // 'claude' removed — default to gemini
            default       => provider_sync_gemini($messages, $system, $maxTokens),
        };
    } catch (Throwable $e) {
        error_log("Solen Router sync error [{$provider}]: " . $e->getMessage());
        return null;
    }
}

function sse_router_error(string $msg): void {
    echo "data: " . json_encode(['type' => 'error', 'message' => $msg]) . "\n\n";
    if (ob_get_level()) ob_flush();
    flush();
}

/**
 * Generic SSE passthrough for cURL CURLOPT_WRITEFUNCTION.
 * Simply echoes the data and flushes buffers.
 */
function sse_passthrough_generic($ch, $data) {
    echo $data;
    if (ob_get_level()) ob_flush();
    flush();
    return strlen($data);
}
