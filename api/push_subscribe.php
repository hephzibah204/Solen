<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$user = require_login_json();
$uid = (int)$user['id'];

$json = json_decode(file_get_contents('php://input'), true);
$endpoint = $json['endpoint'] ?? '';
$keys = $json['keys'] ?? [];
$p256dh = $keys['p256dh'] ?? '';
$auth = $keys['auth'] ?? '';

if (!$endpoint || !$p256dh || !$auth) {
    echo json_encode(['ok' => false, 'error' => 'Missing subscription details']);
    exit;
}

// Upsert: One subscription per endpoint
db_run("INSERT INTO user_push_subscriptions (user_id, endpoint, p256dh, auth) VALUES (?, ?, ?, ?)
        ON CONFLICT(endpoint) DO UPDATE SET user_id=excluded.user_id, p256dh=excluded.p256dh, auth=excluded.auth",
    [$uid, $endpoint, $p256dh, $auth]
);

echo json_encode(['ok' => true]);
