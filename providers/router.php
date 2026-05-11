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
require_once dirname(__DIR__) . '/providers/claude_provider.php';
require_once dirname(__DIR__) . '/providers/puter.php';
require_once dirname(__DIR__) . '/providers/groq.php';
require_once dirname(__DIR__) . '/providers/hypereal.php';

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

    // Emotional routing: crisis situations always go to Claude (most reliable)
    if (($opts['emotional_state'] ?? '') === 'crisis') {
        $provider = 'claude';
        $fallback  = false; // no fallback on crisis; we must not lose the message
    }

    // Auto-select provider if none forced
    if (!$provider) {
        $provider = _select_provider($opts['user_plan'] ?? 'free');
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
    // Admin-configured primary provider
    $configured = strtolower(get_setting('ai_provider', 'claude'));

    // For free users, prefer cheaper providers to reduce cost
    if ($userPlan === 'free') {
        $cheap = get_setting('ai_provider_free', ''); // admin can override
        if ($cheap) return $cheap;
        // Default cost-saving order for free: Puter → Groq → Hypereal → HuggingFace → OpenRouter → Gemini → Claude
        foreach (['puter', 'groq', 'hypereal', 'huggingface', 'openrouter', 'gemini', 'claude'] as $p) {
            if (_provider_has_key($p)) return $p;
        }
    }

    // Paid users get the configured best provider
    if (_provider_has_key($configured)) return $configured;

    // Fallback: first available key
    foreach (['claude', 'gemini', 'openrouter', 'groq', 'hypereal', 'huggingface', 'puter'] as $p) {
        if (_provider_has_key($p)) return $p;
    }

    return 'claude'; // last resort — will error gracefully if no key
}

function _provider_has_key(string $provider): bool {
    return match ($provider) {
        'claude'      => !empty(get_setting('claude_api_key') ?: CLAUDE_API_KEY),
        'gemini'      => !empty(get_setting('gemini_api_key') ?: GEMINI_API_KEY),
        'openrouter'  => !empty(get_setting('openrouter_api_key') ?: OPENROUTER_API_KEY),
        'huggingface' => !empty(get_setting('huggingface_api_key') ?: HUGGINGFACE_API_KEY),
        'groq'        => !empty(get_setting('groq_api_key') ?: GROQ_API_KEY),
        'hypereal'    => !empty(get_setting('hypereal_api_key') ?: HYPEREAL_API_KEY),
        'puter'       => !empty(get_setting('puter_auth_token') ?: (defined('PUTER_AUTH_TOKEN') ? PUTER_AUTH_TOKEN : '')),
        default       => false,
    };
}

/**
 * Build the attempt order: primary first, then sensible fallbacks.
 */
function _build_attempt_order(string $primary, bool $fallback): array {
    if (!$fallback) return [$primary];

    $all = ['claude', 'gemini', 'openrouter', 'groq', 'hypereal', 'huggingface', 'puter'];
    // Primary first, then the rest in reliability order
    return array_merge([$primary], array_values(array_diff($all, [$primary])));
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
            'puter'       => provider_stream_puter($messages, $system, $maxTokens, $opts['model'] ?? null),
            default       => provider_stream_claude($messages, $system, $maxTokens),
        };
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
            'puter'       => provider_sync_puter($messages, $system, $maxTokens, $opts['model'] ?? null),
            default       => provider_sync_claude($messages, $system, $maxTokens),
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
