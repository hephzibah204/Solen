<?php
/**
 * Backward-compatible shim — routes to the new multi-provider ai.php.
 * Old integrations pointing to /api/claude.php continue to work.
 *
 * Fix (C2): php://input is a one-time read stream.
 * We read it ONCE here, inject the provider, then make it available
 * to ai.php via $GLOBALS so ai.php doesn't re-read the empty stream.
 */
$rawInput = file_get_contents('php://input');
$decoded  = json_decode($rawInput, true) ?? [];

// Force Claude provider for backward compatibility
$decoded['provider'] = 'claude';

// Cache the modified payload globally so ai.php can consume it
$GLOBALS['_SOLEN_CACHED_INPUT'] = json_encode($decoded);

require __DIR__ . '/ai.php';
