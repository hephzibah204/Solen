<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Enforce login
$user = require_login_json();
$uid = (int)$user['id'];

/**
 * Solen Data Export Utility
 * Aggregates all user-specific data into a structured JSON for portability.
 */

$export = [
    'user_profile' => [
        'name'       => $user['name'],
        'email'      => $user['email'],
        'plan'       => $user['plan'],
        'created_at' => $user['created_at'],
        'last_login' => $user['last_login']
    ],
    'conversations' => [],
    'mood_logs'     => [],
    'memories'      => [],
    'rituals'       => [],
    'exported_at'   => date('Y-m-d H:i:s')
];

// 1. Conversations (Sessions)
$sessions = db_query("SELECT session_date, messages, message_count, created_at FROM chat_sessions WHERE user_id=? ORDER BY session_date DESC", [$uid]);
foreach ($sessions as $s) {
    $export['conversations'][] = [
        'date'       => $s['session_date'],
        'messages'   => json_decode($s['messages'], true) ?? [],
        'count'      => $s['message_count'],
        'created_at' => $s['created_at']
    ];
}

// 2. Mood Logs
$export['mood_logs'] = db_query("SELECT score, label, emoji, notes, logged_date, created_at FROM mood_logs WHERE user_id=? ORDER BY logged_date DESC", [$uid]);

// 3. Memories (Episodes)
$export['memories'] = db_query("SELECT type, summary, tags, importance, emotional_score, session_date FROM memory_episodes WHERE user_id=? ORDER BY created_at DESC", [$uid]);

// 4. Rituals/Streaks
$export['rituals'] = db_query("SELECT ritual_id, status, created_at FROM ritual_completions WHERE user_id=? ORDER BY created_at DESC", [$uid]);

// Output as JSON file
header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="solen_data_export_' . date('Ymd') . '.json"');

echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;
