<?php
require 'includes/db.php';
$_SESSION['user_id'] = 1; // Assuming user 1 exists
$body = [
    'system' => 'test system prompt',
    'messages' => [['role' => 'user', 'content' => 'hello']],
    'provider' => 'auto'
];
$GLOBALS['_SOLEN_CACHED_INPUT'] = json_encode($body);
$_SERVER['REQUEST_METHOD'] = 'POST';

ob_start();
require 'api/ai.php';
$out = ob_get_clean();
echo "Response Output:\n$out\n";
