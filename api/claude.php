<?php
/**
 * Backward-compatible shim — routes to the smart AI router.
 * Old integrations pointing to /api/claude.php continue to work.
 * Claude has been removed; requests are routed via smart auto-selection.
 */
$rawInput = file_get_contents('php://input');
$decoded  = json_decode($rawInput, true) ?? [];

// Remove any explicit 'claude' provider — let the smart router pick the best one
if (isset($decoded['provider']) && $decoded['provider'] === 'claude') {
    unset($decoded['provider']); // Smart router will auto-select
}

// Cache the modified payload globally so ai.php can consume it
$GLOBALS['_SOLEN_CACHED_INPUT'] = json_encode($decoded);

require __DIR__ . '/ai.php';
