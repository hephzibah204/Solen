<?php
/**
 * /api/cron.php — Daily job runner (Phase 5 update)
 *
 * Call from server cron (once per day, any time):
 *   curl -s -H "X-Cron-Secret: YOUR_SECRET" https://yourdomain.com/api/cron.php
 *
 * Recommended schedule:
 *   0 1 * * * curl -s -H "X-Cron-Secret: YOUR_SECRET" https://yourdomain.com/api/cron.php >> /var/log/solen-cron.log 2>&1
 *
 * Jobs:
 *   1–2. Trial expiry warning emails (3d / 1d)
 *   3.   Daily check-in reminders
 *   4.   Clean up expired password reset tokens
 *   5.   [Phase 3] Nightly session memory compression
 *   6.   [Phase 3] Memory re-ranking (recency decay)
 *   7.   [Phase 5] Growth analytics snapshots for active users
 *   8.   [Phase 5] Process due adaptive reminders
 *   9.   [Phase 5] Schedule adaptive reminders for tomorrow
 */

require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/prompt_engine.php';
require_once dirname(__DIR__) . '/includes/memory.php';
require_once dirname(__DIR__) . '/includes/retention.php';
require_once dirname(__DIR__) . '/providers/router.php';

set_time_limit(0); // Prevent timeouts for long-running cron jobs


// ── Authentication ──────────────────────────────────────────────────────────
$secret   = get_setting('cron_secret');
$provided = $_SERVER['HTTP_X_CRON_SECRET'] ?? '';

if (!$secret || strlen($secret) < 32 || !hash_equals($secret, $provided)) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

header('Content-Type: application/json');

$results = [];

// ── Job 1: Trial warning emails ─────────────────────────────────────────────
$sent3 = send_trial_warning_emails(3);
$results['trial_warning_3day'] = $sent3;

$sent1 = send_trial_warning_emails(1);
$results['trial_warning_1day'] = $sent1;

// ── Job 2: Daily check-in reminders ────────────────────────────────────────
if (get_setting('checkin_reminders_enabled', '1') === '1') {
    $results['checkin_reminders_sent'] = send_checkin_reminder_emails();
} else {
    $results['checkin_reminders_sent'] = 'disabled';
}

// ── Job 3: Clean up expired password reset tokens ───────────────────────────
$cleaned = db_run(
    "DELETE FROM password_resets WHERE expires_at < datetime('now') AND used_at IS NULL"
);
$results['expired_tokens_cleaned'] = $cleaned;

// ── Job 4: Log run ──────────────────────────────────────────────────────────
$results['run_at'] = date('Y-m-d H:i:s');
$results['status'] = 'ok';

// ── Job 5: Phase 3 — Nightly Memory Compression ─────────────────────────────
if (get_setting('memory_compress_enabled', '1') === '1') {
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $activeSessions = db_query(
        "SELECT DISTINCT user_id FROM chat_sessions WHERE session_date=? AND message_count > 1",
        [$yesterday]
    );
    $compressed = 0;
    foreach ($activeSessions as $row) {
        try {
            memory_compress_session((int)$row['user_id'], $yesterday);
            $compressed++;
        } catch (Throwable $e) {
            error_log("Solen cron: memory compression failed for user {$row['user_id']}: " . $e->getMessage());
        }
    }
    $results['memory_sessions_compressed'] = $compressed;
} else {
    $results['memory_sessions_compressed'] = 'disabled';
}

// ── Job 6: Phase 3 — Rerank memories for active users ──────────────────────
$activeUsers = db_query(
    "SELECT DISTINCT user_id FROM memory_episodes WHERE created_at > datetime('now', '-60 days')"
);
$reranked = 0;
foreach ($activeUsers as $row) {
    $episodes = db_query(
        "SELECT id FROM memory_episodes WHERE user_id=?",
        [(int)$row['user_id']]
    );
    foreach ($episodes as $ep) {
        _memory_rerank_episode((int)$ep['id']);
        $reranked++;
    }
}
$results['memories_reranked'] = $reranked;

// ── Job 7: Phase 5 — Growth Analytics Snapshots ─────────────────────────────
// Compute and store nightly growth snapshot for every user active in last 30 days.
if (get_setting('analytics_snapshots_enabled', '1') === '1') {
    $activeUsers = db_query(
        "SELECT DISTINCT user_id FROM mood_logs WHERE logged_date >= date('now', '-30 days')"
    );
    $snapshots = 0;
    foreach ($activeUsers as $row) {
        try {
            analytics_compute_and_store((int)$row['user_id']);
            $snapshots++;
        } catch (Throwable $e) {
            error_log("Solen cron: analytics snapshot failed for user {$row['user_id']}: " . $e->getMessage());
        }
    }
    $results['analytics_snapshots'] = $snapshots;
} else {
    $results['analytics_snapshots'] = 'disabled';
}

// ── Job 8: Phase 5 — Process Due Adaptive Reminders ─────────────────────────
if (get_setting('adaptive_reminders_enabled', '1') === '1') {
    $results['reminders_sent'] = reminder_process_due();
} else {
    $results['reminders_sent'] = 'disabled';
}

// ── Job 9: Phase 5 — Schedule Tomorrow's Adaptive Reminders ─────────────────
// Pre-schedule reminders for all users who were active in the last 7 days.
if (get_setting('adaptive_reminders_enabled', '1') === '1') {
    $recentUsers = db_query(
        "SELECT DISTINCT user_id FROM mood_logs WHERE logged_date >= date('now', '-7 days')"
    );
    $scheduled = 0;
    foreach ($recentUsers as $row) {
        try {
            reminder_schedule_adaptive((int)$row['user_id']);
            $scheduled++;
        } catch (Throwable $e) {
            error_log("Solen cron: reminder scheduling failed for user {$row['user_id']}: " . $e->getMessage());
        }
    }
    $results['reminders_scheduled'] = $scheduled;
} else {
    $results['reminders_scheduled'] = 'disabled';
}

// ── Job 10: SQLite Power Maintenance & Backup ──────────────────────────────
$backupDir = dirname(__DIR__) . '/backups';
if (!is_dir($backupDir)) mkdir($backupDir, 0700, true);
$results['db_backup'] = db_backup($backupDir) ? 'success' : 'failed';
$results['db_optimization'] = db_optimize();

echo json_encode($results, JSON_PRETTY_PRINT);
