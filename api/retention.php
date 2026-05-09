<?php
/**
 * /api/retention.php — Phase 5 Behavioral Retention API
 *
 * All endpoints require authentication (session cookie).
 *
 * GET  ?action=rituals[&period=morning|evening|weekly]
 *      → List rituals for a period with today's completion status
 *
 * GET  ?action=ritual_status
 *      → Today's overall ritual completion status
 *
 * GET  ?action=streak
 *      → Ritual streak data
 *
 * POST ?action=complete_ritual
 *      { "ritual_key": "morning_checkin", "note": "..." }
 *
 * GET  ?action=timeline[&days=90]
 *      → Emotional timeline data points
 *
 * GET  ?action=milestones
 *      → All milestone moments
 *
 * GET  ?action=analytics
 *      → Growth summary
 *
 * GET  ?action=mood_trend[&days=30]
 *      → Daily mood averages
 *
 * GET  ?action=consistency
 *      → Ritual / check-in consistency metrics
 *
 * GET  ?action=next_reminder
 *      → Next scheduled reminder for user
 */

require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/retention.php';

header('Content-Type: application/json');

// ── Auth ────────────────────────────────────────────────────────────────────
$user = require_login_json(); // returns user array or exits with 401
$userId = (int)$user['id'];

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ── POST body ────────────────────────────────────────────────────────────────
$body = [];
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true) ?? [];
}

try {
    switch ($action) {

        // ── Rituals ──────────────────────────────────────────────────────────

        case 'rituals':
            $period = $_GET['period'] ?? 'morning';
            echo json_encode([
                'ok'      => true,
                'period'  => $period,
                'rituals' => ritual_get_for_user($userId, $period),
            ]);
            break;

        case 'ritual_status':
            echo json_encode([
                'ok'     => true,
                'status' => ritual_get_today_status($userId),
            ]);
            break;

        case 'streak':
            echo json_encode([
                'ok'     => true,
                'streak' => ritual_get_streak($userId),
            ]);
            break;

        case 'complete_ritual':
            if ($method !== 'POST') { http_response_code(405); echo json_encode(['error' => 'POST required']); break; }

            $key  = trim($body['ritual_key'] ?? '');
            $note = trim($body['note'] ?? '') ?: null;

            if (!$key) { http_response_code(400); echo json_encode(['error' => 'ritual_key required']); break; }

            $ok = ritual_complete($userId, $key, $note);

            // Log mood if score provided (optional from frontend)
            if ($ok && isset($body['mood_score'])) {
                $score = max(1, min(10, (int)$body['mood_score']));
                db_run(
                    "INSERT INTO mood_logs (user_id, score, notes, logged_date) VALUES (?,?,?,date('now'))",
                    [$userId, $score, $note]
                );
            }

            echo json_encode([
                'ok'     => $ok,
                'streak' => ritual_get_streak($userId),
                'status' => ritual_get_today_status($userId),
            ]);
            break;

        // ── Timeline ─────────────────────────────────────────────────────────

        case 'timeline':
            $days = max(7, min(365, (int)($_GET['days'] ?? 90)));
            echo json_encode([
                'ok'       => true,
                'days'     => $days,
                'timeline' => timeline_get($userId, $days),
            ]);
            break;

        case 'milestones':
            echo json_encode([
                'ok'         => true,
                'milestones' => timeline_get_milestones($userId),
            ]);
            break;

        // ── Analytics ────────────────────────────────────────────────────────

        case 'analytics':
            echo json_encode([
                'ok'      => true,
                'summary' => analytics_get_summary($userId),
            ]);
            break;

        case 'mood_trend':
            $days = max(7, min(180, (int)($_GET['days'] ?? 30)));
            echo json_encode([
                'ok'    => true,
                'days'  => $days,
                'trend' => analytics_get_mood_trend($userId, $days),
            ]);
            break;

        case 'consistency':
            echo json_encode([
                'ok'          => true,
                'consistency' => analytics_get_consistency($userId),
            ]);
            break;

        // ── Reminders ────────────────────────────────────────────────────────

        case 'next_reminder':
            echo json_encode([
                'ok'       => true,
                'reminder' => reminder_get_next($userId),
            ]);
            break;

        // ── Ritual preferences (toggle on/off) ───────────────────────────────

        case 'set_ritual_pref':
            if ($method !== 'POST') { http_response_code(405); echo json_encode(['error' => 'POST required']); break; }
            $key     = trim($body['ritual_key'] ?? '');
            $enabled = isset($body['enabled']) ? (int)(bool)$body['enabled'] : 1;
            if (!$key) { http_response_code(400); echo json_encode(['error' => 'ritual_key required']); break; }

            db_run(
                "INSERT OR REPLACE INTO ritual_preferences (user_id, ritual_key, enabled) VALUES (?,?,?)",
                [$userId, $key, $enabled]
            );
            echo json_encode(['ok' => true]);
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Unknown action: ' . htmlspecialchars($action)]);
    }

} catch (Throwable $e) {
    error_log("Solen retention API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal error']);
}
