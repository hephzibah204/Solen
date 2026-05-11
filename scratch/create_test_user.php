<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$email = 'testuser@example.com';
$password = 'Password123!';
$name = 'Test User';

// Check if user exists
$existing = db_one("SELECT id FROM users WHERE email = ?", [$email]);
if ($existing) {
    echo "User already exists.\n";
} else {
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    db_run("INSERT INTO users (email, password, name, role, plan, trial_ends) VALUES (?, ?, ?, 'user', 'free', ?)", [
        $email, $hashed, $name, date('Y-m-d H:i:s', strtotime('+7 days'))
    ]);
    echo "User created successfully.\n";
}
