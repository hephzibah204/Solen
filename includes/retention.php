<?php
/**
 * Solen — Phase 5: Behavioral Retention System
 *
 * Ritual System      → morning/evening/weekly rituals with completion tracking
 * Emotional Timeline → visualisable mood/breakthrough history
 * Growth Analytics   → improvement scores, journaling streaks, recovery metrics
 * Smart Reminders    → adaptive, emotionally-aware push/email nudges
 *
 * Public API (called from app.php / api/retention.php):
 *
 *   retention_run_migrations(PDO $pdo)
 *
 *   // Rituals
 *   ritual_get_for_user(int $userId, string $period = 'morning'): array
 *   ritual_complete(int $userId, string $ritualKey, ?string $note = null): bool
 *   ritual_get_today_status(int $userId): array
 *   ritual_get_streak(int $userId): array
 *
 *   // Emotional Timeline
 *   timeline_get(int $userId, int $days = 90): array
 *   timeline_get_milestones(int $userId): array
 *
 *   // Growth Analytics
 *   analytics_get_summary(int $userId): array
 *   analytics_get_mood_trend(int $userId, int $days = 30): array
 *   analytics_get_consistency(int $userId): array
 *
 *   // Smart Reminders
 *   reminder_schedule_adaptive(int $userId): void
 *   reminder_process_due(): int
 *   reminder_get_next(int $userId): ?array
 */

// ── CONSTANTS ────────────────────────────────────────────────────────────────

const RITUAL_PERIODS = ['morning', 'evening', 'weekly'];

const RITUAL_DEFAULTS = [
    'morning' => [
        ['key' => 'morning_checkin',    'label' => 'Morning Check-in',     'description' => 'How are you feeling as the day begins?',          'icon' => '🌅', 'duration_min' => 2],
        ['key' => 'morning_intention',  'label' => 'Set Your Intention',   'description' => 'What one thing matters most to you today?',        'icon' => '🎯', 'duration_min' => 2],
        ['key' => 'morning_gratitude',  'label' => 'Gratitude Moment',     'description' => 'Name one thing you\'re grateful for right now.',   'icon' => '✨', 'duration_min' => 1],
    ],
    'evening' => [
        ['key' => 'evening_reflection', 'label' => 'Evening Reflection',   'description' => 'How did today go emotionally?',                    'icon' => '🌙', 'duration_min' => 3],
        ['key' => 'evening_release',    'label' => 'Release What\'s Heavy','description' => 'What can you let go of before you sleep?',         'icon' => '🕊️', 'duration_min' => 2],
        ['key' => 'evening_win',        'label' => 'Celebrate a Win',      'description' => 'What went well today, no matter how small?',       'icon' => '🏆', 'duration_min' => 1],
    ],
    'weekly' => [
        ['key' => 'weekly_review',      'label' => 'Weekly Review',        'description' => 'Look back at this week\'s emotional journey.',     'icon' => '📊', 'duration_min' => 5],
        ['key' => 'weekly_growth',      'label' => 'Growth Reflection',    'description' => 'Where did you grow this week?',                   'icon' => '🌱', 'duration_min' => 3],
        ['key' => 'weekly_intention',   'label' => 'Week Ahead Intention', 'description' => 'What emotional intention will guide next week?',   'icon' => '🗺️', 'duration_min' => 2],
    ],
];

// Score weights for growth analytics
const ANALYTICS_WEIGHTS = [
    'checkin_streak'      => 0.25,
    'mood_improvement'    => 0.30,
    'ritual_consistency'  => 0.20,
    'memory_breakthroughs'=> 0.15,
    'recovery_speed'      => 0.10,
];

// ── MIGRATIONS ───────────────────────────────────────────────────────────────

function retention_run_migrations(PDO $pdo): void
{
    // Ritual completions
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ritual_completions (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id     INTEGER NOT NULL,
            ritual_key  TEXT    NOT NULL,
            period      TEXT    NOT NULL DEFAULT 'morning',
            note        TEXT,
            completed_at TEXT   NOT NULL DEFAULT (datetime('now')),
            date        TEXT    NOT NULL DEFAULT (date('now'))
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ritual_user_date ON ritual_completions(user_id, date)");

    // User ritual preferences (enabled/disabled per ritual)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ritual_preferences (
            user_id    INTEGER NOT NULL,
            ritual_key TEXT    NOT NULL,
            enabled    INTEGER NOT NULL DEFAULT 1,
            custom_time TEXT,
            PRIMARY KEY (user_id, ritual_key)
        )
    ");

    // Emotional timeline milestones (surfaced breakthrough moments)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS timeline_milestones (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id      INTEGER NOT NULL,
            type         TEXT    NOT NULL,  -- 'streak','mood_high','breakthrough','recovery','first_ritual'
            title        TEXT    NOT NULL,
            description  TEXT,
            metric_value REAL,
            created_at   TEXT    NOT NULL DEFAULT (datetime('now')),
            date         TEXT    NOT NULL DEFAULT (date('now'))
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_milestone_user ON timeline_milestones(user_id, date)");

    // Adaptive reminder schedules
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reminder_schedules (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id      INTEGER NOT NULL,
            reminder_type TEXT   NOT NULL,  -- 'ritual','checkin','inactivity','stress'
            channel      TEXT    NOT NULL DEFAULT 'email',  -- 'email','push'
            scheduled_at TEXT    NOT NULL,
            sent_at      TEXT,
            suppressed   INTEGER NOT NULL DEFAULT 0,
            suppress_reason TEXT,
            created_at   TEXT    NOT NULL DEFAULT (datetime('now'))
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_reminder_due ON reminder_schedules(scheduled_at, sent_at)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_reminder_user ON reminder_schedules(user_id)");

    // Growth analytics snapshots (computed nightly)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS growth_snapshots (
            id                INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id           INTEGER NOT NULL,
            date              TEXT    NOT NULL,
            overall_score     REAL    NOT NULL DEFAULT 0,
            checkin_streak    INTEGER NOT NULL DEFAULT 0,
            ritual_streak     INTEGER NOT NULL DEFAULT 0,
            mood_avg_7d       REAL,
            mood_avg_30d      REAL,
            mood_delta        REAL,
            breakthroughs_30d INTEGER NOT NULL DEFAULT 0,
            recovery_speed    REAL,
            journal_entries   INTEGER NOT NULL DEFAULT 0,
            created_at        TEXT    NOT NULL DEFAULT (datetime('now')),
            UNIQUE(user_id, date)
        )
    ");
}

// ── RITUALS ──────────────────────────────────────────────────────────────────

/**
 * Get rituals for a user for a given period, with today's completion status.
 */
function ritual_get_for_user(int $userId, string $period = 'morning'): array
{
    if (!in_array($period, RITUAL_PERIODS, true)) $period = 'morning';

    $defaults = RITUAL_DEFAULTS[$period] ?? [];
    $today    = date('Y-m-d');

    // Completed today
    $done = db_query(
        "SELECT ritual_key FROM ritual_completions WHERE user_id=? AND date=? AND period=?",
        [$userId, $today, $period]
    );
    $doneKeys = array_column($done, 'ritual_key');

    // Preferences
    $prefs = db_query(
        "SELECT ritual_key, enabled FROM ritual_preferences WHERE user_id=?",
        [$userId]
    );
    $prefMap = [];
    foreach ($prefs as $p) $prefMap[$p['ritual_key']] = (bool)$p['enabled'];

    $result = [];
    foreach ($defaults as $r) {
        $enabled   = $prefMap[$r['key']] ?? true;
        $completed = in_array($r['key'], $doneKeys, true);
        $result[]  = array_merge($r, [
            'period'    => $period,
            'enabled'   => $enabled,
            'completed' => $completed,
        ]);
    }
    return $result;
}

/**
 * Mark a ritual as completed for today.
 */
function ritual_complete(int $userId, string $ritualKey, ?string $note = null): bool
{
    $today  = date('Y-m-d');
    $period = _ritual_key_to_period($ritualKey);

    // Idempotent — don't double-insert
    $existing = db_query(
        "SELECT id FROM ritual_completions WHERE user_id=? AND ritual_key=? AND date=?",
        [$userId, $ritualKey, $today]
    );
    if ($existing) return true;

    db_run(
        "INSERT INTO ritual_completions (user_id, ritual_key, period, note, date) VALUES (?,?,?,?,?)",
        [$userId, $ritualKey, $period, $note, $today]
    );

    // Check for streak milestones
    _ritual_maybe_award_milestone($userId);

    return true;
}

/**
 * Today's completion status across all periods.
 */
function ritual_get_today_status(int $userId): array
{
    $today = date('Y-m-d');
    $done  = db_query(
        "SELECT ritual_key, period, note, completed_at FROM ritual_completions WHERE user_id=? AND date=?",
        [$userId, $today]
    );

    $total    = 0;
    $complete = count($done);
    foreach (RITUAL_PERIODS as $p) $total += count(RITUAL_DEFAULTS[$p]);

    return [
        'date'       => $today,
        'completed'  => $complete,
        'total'      => $total,
        'pct'        => $total ? round(($complete / $total) * 100) : 0,
        'by_period'  => _ritual_group_by_period($done),
        'items'      => $done,
    ];
}

/**
 * Current ritual streak (consecutive days with ≥1 ritual completed).
 */
function ritual_get_streak(int $userId): array
{
    $rows = db_query(
        "SELECT DISTINCT date FROM ritual_completions WHERE user_id=? ORDER BY date DESC",
        [$userId]
    );

    $streak   = 0;
    $today    = date('Y-m-d');
    $expected = $today;

    foreach ($rows as $row) {
        if ($row['date'] === $expected) {
            $streak++;
            $expected = date('Y-m-d', strtotime($expected . ' -1 day'));
        } else {
            break;
        }
    }

    // Longest ever
    $longest  = 0;
    $current  = 0;
    $prevDate = null;
    foreach (array_reverse($rows) as $row) {
        if ($prevDate === null || $row['date'] === date('Y-m-d', strtotime($prevDate . ' +1 day'))) {
            $current++;
        } else {
            $current = 1;
        }
        if ($current > $longest) $longest = $current;
        $prevDate = $row['date'];
    }

    return [
        'current' => $streak,
        'longest' => $longest,
        'total_days' => count($rows),
    ];
}

// ── EMOTIONAL TIMELINE ───────────────────────────────────────────────────────

/**
 * Build the emotional timeline for visualisation.
 * Returns daily data points with mood score, events, and flags.
 */
function timeline_get(int $userId, int $days = 90): array
{
    $since = date('Y-m-d', strtotime("-{$days} days"));

    // Mood data from mood_logs
    $moods = db_query(
        "SELECT logged_date as day, AVG(score) as avg_mood, COUNT(*) as entries
         FROM mood_logs WHERE user_id=? AND logged_date >= ? GROUP BY logged_date ORDER BY day ASC",
        [$userId, $since]
    );

    // Ritual completions per day
    $ritualCounts = db_query(
        "SELECT date, COUNT(*) as cnt FROM ritual_completions WHERE user_id=? AND date >= ? GROUP BY date",
        [$userId, $since]
    );
    $ritualMap = [];
    foreach ($ritualCounts as $r) $ritualMap[$r['date']] = (int)$r['cnt'];

    // Breakthroughs (from memory_episodes)
    $breakthroughs = db_query(
        "SELECT date(created_at) as day FROM memory_episodes
         WHERE user_id=? AND type='breakthrough' AND created_at >= ?",
        [$userId, $since]
    );
    $breakthroughDays = array_flip(array_column($breakthroughs, 'day'));

    // Milestones
    $milestones = db_query(
        "SELECT date, type, title FROM timeline_milestones WHERE user_id=? AND date >= ? ORDER BY date ASC",
        [$userId, $since]
    );
    $milestoneMap = [];
    foreach ($milestones as $m) $milestoneMap[$m['date']][] = $m;

    // Build output indexed by day
    $timeline = [];
    foreach ($moods as $row) {
        $day = $row['day'];
        $timeline[$day] = [
            'date'          => $day,
            'mood_avg'      => round((float)$row['avg_mood'], 2),
            'entries'       => (int)$row['entries'],
            'rituals_done'  => $ritualMap[$day] ?? 0,
            'breakthrough'  => isset($breakthroughDays[$day]),
            'milestones'    => $milestoneMap[$day] ?? [],
        ];
    }

    // Fill missing days with null mood (for chart continuity)
    $pointer = $since;
    $today   = date('Y-m-d');
    $filled  = [];
    while ($pointer <= $today) {
        $filled[] = $timeline[$pointer] ?? [
            'date'         => $pointer,
            'mood_avg'     => null,
            'entries'      => 0,
            'rituals_done' => $ritualMap[$pointer] ?? 0,
            'breakthrough' => false,
            'milestones'   => $milestoneMap[$pointer] ?? [],
        ];
        $pointer = date('Y-m-d', strtotime($pointer . ' +1 day'));
    }

    return $filled;
}

/**
 * Get all milestone moments for a user.
 */
function timeline_get_milestones(int $userId): array
{
    return db_query(
        "SELECT * FROM timeline_milestones WHERE user_id=? ORDER BY date DESC",
        [$userId]
    );
}

// ── GROWTH ANALYTICS ─────────────────────────────────────────────────────────

/**
 * Compute a full growth summary for the user.
 */
function analytics_get_summary(int $userId): array
{
    $snapshot = _analytics_latest_snapshot($userId);
    if ($snapshot) return $snapshot;
    return _analytics_compute($userId);
}

/**
 * Mood trend: daily averages for the last N days.
 */
function analytics_get_mood_trend(int $userId, int $days = 30): array
{
    $since = date('Y-m-d', strtotime("-{$days} days"));
    return db_query(
        "SELECT logged_date as day, AVG(score) as avg_mood, MIN(score) as min_mood, MAX(score) as max_mood
         FROM mood_logs WHERE user_id=? AND logged_date >= ? GROUP BY logged_date ORDER BY day ASC",
        [$userId, $since]
    );
}

/**
 * Consistency metrics: ritual streak, journal frequency, check-in regularity.
 */
function analytics_get_consistency(int $userId): array
{
    $streaks  = ritual_get_streak($userId);
    $last30   = date('Y-m-d', strtotime('-30 days'));

    $checkins = db_query(
        "SELECT COUNT(DISTINCT logged_date) as cnt FROM mood_logs WHERE user_id=? AND logged_date >= ?",
        [$userId, $last30]
    );
    $journals = db_query(
        "SELECT COUNT(*) as cnt FROM voice_journals WHERE user_id=? AND created_at >= ?",
        [$userId, $last30]
    );

    $activeDays   = (int)($checkins[0]['cnt'] ?? 0);
    $journalCount = (int)($journals[0]['cnt'] ?? 0);

    return [
        'ritual_streak_current'  => $streaks['current'],
        'ritual_streak_longest'  => $streaks['longest'],
        'checkin_days_last_30'   => $activeDays,
        'checkin_consistency_pct'=> round(($activeDays / 30) * 100),
        'journal_entries_last_30'=> $journalCount,
        'journal_frequency'      => $journalCount > 0 ? round(30 / $journalCount, 1) : null, // days between journals
    ];
}

// ── SMART REMINDERS ──────────────────────────────────────────────────────────

/**
 * Schedule adaptive reminders for a user based on their patterns.
 * Called after login or by cron.
 */
function reminder_schedule_adaptive(int $userId): void
{
    $today     = date('Y-m-d');
    $patterns  = _reminder_get_user_patterns($userId);
    $scheduled = [];

    // 1. Morning ritual reminder — if user hasn't done morning ritual by 9 AM
    $morningDone = _ritual_period_done_today($userId, 'morning');
    if (!$morningDone) {
        $hour = (int)date('G');
        if ($hour < 9) {
            $scheduled[] = [
                'user_id'       => $userId,
                'reminder_type' => 'ritual_morning',
                'channel'       => 'email',
                'scheduled_at'  => "{$today} 09:00:00",
            ];
        }
    }

    // 2. Evening ritual reminder — 8 PM if evening not done
    $eveningDone = _ritual_period_done_today($userId, 'evening');
    if (!$eveningDone) {
        $scheduled[] = [
            'user_id'       => $userId,
            'reminder_type' => 'ritual_evening',
            'channel'       => 'email',
            'scheduled_at'  => "{$today} 20:00:00",
        ];
    }

    // 3. Inactivity reminder — if user hasn't opened app in 2+ days
    if (($patterns['days_inactive'] ?? 0) >= 2) {
        $emotionalNudge = _reminder_inactivity_message($patterns);
        $scheduled[] = [
            'user_id'       => $userId,
            'reminder_type' => 'inactivity',
            'channel'       => 'email',
            'scheduled_at'  => date('Y-m-d H:i:s', strtotime('+2 hours')),
        ];
    }

    // 4. Stress-based reminder — if burnout/high anxiety detected
    if (in_array($patterns['dominant_state'] ?? '', ['burnout', 'anxiety_high', 'high_distress'], true)) {
        $scheduled[] = [
            'user_id'       => $userId,
            'reminder_type' => 'stress_support',
            'channel'       => 'email',
            'scheduled_at'  => date('Y-m-d H:i:s', strtotime('+4 hours')),
        ];
    }

    foreach ($scheduled as $r) {
        // Don't double-schedule the same type today
        $exists = db_query(
            "SELECT id FROM reminder_schedules WHERE user_id=? AND reminder_type=? AND scheduled_at LIKE ?",
            [$r['user_id'], $r['reminder_type'], "{$today}%"]
        );
        if (!$exists) {
            db_run(
                "INSERT INTO reminder_schedules (user_id, reminder_type, channel, scheduled_at) VALUES (?,?,?,?)",
                [$r['user_id'], $r['reminder_type'], $r['channel'], $r['scheduled_at']]
            );
        }
    }
}

/**
 * Process all due reminders. Returns count sent.
 * Called from cron.
 */
function reminder_process_due(): int
{
    $now = date('Y-m-d H:i:s');
    $due = db_query(
        "SELECT r.*, u.email, u.name, cp.coach_name
         FROM reminder_schedules r
         JOIN users u ON u.id = r.user_id
         LEFT JOIN coach_profiles cp ON cp.user_id = r.user_id
         WHERE r.scheduled_at <= ? AND r.sent_at IS NULL AND r.suppressed = 0",
        [$now]
    );

    $sent = 0;
    foreach ($due as $r) {
        try {
            $ok = _reminder_send($r);
            if ($ok) {
                db_run("UPDATE reminder_schedules SET sent_at=datetime('now') WHERE id=?", [$r['id']]);
                $sent++;
            }
        } catch (Throwable $e) {
            error_log("Solen reminder send failed #{$r['id']}: " . $e->getMessage());
        }
    }
    return $sent;
}

/**
 * Get next upcoming reminder for a user.
 */
function reminder_get_next(int $userId): ?array
{
    $rows = db_query(
        "SELECT * FROM reminder_schedules WHERE user_id=? AND sent_at IS NULL AND suppressed=0 ORDER BY scheduled_at ASC LIMIT 1",
        [$userId]
    );
    return $rows[0] ?? null;
}

// ── NIGHTLY ANALYTICS SNAPSHOT ───────────────────────────────────────────────

/**
 * Compute and store a growth snapshot for a user.
 * Called from cron (Job 7).
 */
function analytics_compute_and_store(int $userId): void
{
    $data = _analytics_compute($userId);
    $today = date('Y-m-d');

    db_run(
        "INSERT OR REPLACE INTO growth_snapshots
            (user_id, date, overall_score, checkin_streak, ritual_streak,
             mood_avg_7d, mood_avg_30d, mood_delta, breakthroughs_30d, recovery_speed, journal_entries)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)",
        [
            $userId,
            $today,
            $data['overall_score'],
            $data['checkin_streak'],
            $data['ritual_streak'],
            $data['mood_avg_7d'],
            $data['mood_avg_30d'],
            $data['mood_delta'],
            $data['breakthroughs_30d'],
            $data['recovery_speed'],
            $data['journal_entries'],
        ]
    );

    // Award milestones based on snapshot
    _analytics_maybe_award_milestones($userId, $data);
}

// ── INTERNAL HELPERS ─────────────────────────────────────────────────────────

function _ritual_key_to_period(string $key): string
{
    foreach (RITUAL_DEFAULTS as $period => $rituals) {
        foreach ($rituals as $r) {
            if ($r['key'] === $key) return $period;
        }
    }
    return 'morning';
}

function _ritual_group_by_period(array $rows): array
{
    $out = ['morning' => [], 'evening' => [], 'weekly' => []];
    foreach ($rows as $r) {
        $p = $r['period'] ?? 'morning';
        if (isset($out[$p])) $out[$p][] = $r;
    }
    return $out;
}

function _ritual_period_done_today(int $userId, string $period): bool
{
    $today = date('Y-m-d');
    $rows  = db_query(
        "SELECT id FROM ritual_completions WHERE user_id=? AND period=? AND date=? LIMIT 1",
        [$userId, $period, $today]
    );
    return !empty($rows);
}

function _ritual_maybe_award_milestone(int $userId): void
{
    $streak = ritual_get_streak($userId);
    $milestones = [3 => '3-Day Ritual Streak 🔥', 7 => 'One Week of Rituals ⭐', 30 => '30-Day Ritual Master 🏆'];
    foreach ($milestones as $days => $title) {
        if ($streak['current'] === $days) {
            _milestone_award($userId, 'streak', $title, "You've completed rituals {$days} days in a row.", $days);
        }
    }
}

function _milestone_award(int $userId, string $type, string $title, string $desc, float $value = 0): void
{
    $today = date('Y-m-d');
    // Don't duplicate same type+title on same day
    $exists = db_query(
        "SELECT id FROM timeline_milestones WHERE user_id=? AND type=? AND title=? AND date=?",
        [$userId, $type, $title, $today]
    );
    if ($exists) return;
    db_run(
        "INSERT INTO timeline_milestones (user_id, type, title, description, metric_value) VALUES (?,?,?,?,?)",
        [$userId, $type, $title, $desc, $value]
    );
}

function _analytics_compute(int $userId): array
{
    $today  = date('Y-m-d');
    $d7ago  = date('Y-m-d', strtotime('-7 days'));
    $d30ago = date('Y-m-d', strtotime('-30 days'));

    // Mood averages
    $mood7 = db_query(
        "SELECT AVG(score) as avg FROM mood_logs WHERE user_id=? AND logged_date >= ?",
        [$userId, $d7ago]
    );
    $mood30 = db_query(
        "SELECT AVG(score) as avg FROM mood_logs WHERE user_id=? AND logged_date >= ?",
        [$userId, $d30ago]
    );
    $moodPrev30 = db_query(
        "SELECT AVG(score) as avg FROM mood_logs WHERE user_id=? AND logged_date BETWEEN ? AND ?",
        [$userId, date('Y-m-d', strtotime('-60 days')), $d30ago]
    );

    $avg7  = isset($mood7[0]['avg'])     ? round((float)$mood7[0]['avg'], 2)     : null;
    $avg30 = isset($mood30[0]['avg'])    ? round((float)$mood30[0]['avg'], 2)    : null;
    $prev  = isset($moodPrev30[0]['avg'])? round((float)$moodPrev30[0]['avg'], 2): null;
    $delta = ($avg30 !== null && $prev !== null) ? round($avg30 - $prev, 2) : null;

    // Streaks
    $streaks    = ritual_get_streak($userId);
    $checkinRows= db_query(
        "SELECT COUNT(DISTINCT logged_date) as cnt FROM mood_logs WHERE user_id=? AND logged_date >= ?",
        [$userId, $d30ago]
    );
    $checkinStreak = (int)($checkinRows[0]['cnt'] ?? 0);

    // Breakthroughs
    $btRows = db_query(
        "SELECT COUNT(*) as cnt FROM memory_episodes WHERE user_id=? AND type='breakthrough' AND created_at >= ?",
        [$userId, $d30ago]
    );
    $breakthroughs = (int)($btRows[0]['cnt'] ?? 0);

    // Recovery speed: avg days from high_distress episode to positive mood
    $recoveryRows = db_query(
        "SELECT created_at FROM memory_emotional_patterns
         WHERE user_id=? AND pattern_type LIKE '%burnout%' AND resolved_at IS NOT NULL
         ORDER BY resolved_at DESC LIMIT 5",
        [$userId]
    );
    $recoverySpeed = null;
    if ($recoveryRows) {
        $speeds = [];
        foreach ($recoveryRows as $rec) {
            // Rough heuristic: count days pattern was active
            $speeds[] = 7; // placeholder — real impl compares created_at/resolved_at
        }
        $recoverySpeed = $speeds ? round(array_sum($speeds) / count($speeds), 1) : null;
    }

    // Journal entries
    $jRows = db_query(
        "SELECT COUNT(*) as cnt FROM voice_journals WHERE user_id=? AND created_at >= ?",
        [$userId, $d30ago]
    );
    $journals = (int)($jRows[0]['cnt'] ?? 0);

    // Overall score (0–100)
    $moodImprovement = ($delta !== null && $delta > 0) ? min($delta * 10, 1.0) : 0;
    $ritualConsistency = $streaks['current'] > 0 ? min($streaks['current'] / 30, 1.0) : 0;
    $checkinConsistency= min($checkinStreak / 30, 1.0);
    $btScore = min($breakthroughs / 3, 1.0);
    $recoveryScore = ($recoverySpeed !== null) ? max(0, 1 - ($recoverySpeed / 14)) : 0;

    $overall = (
        $checkinConsistency  * ANALYTICS_WEIGHTS['checkin_streak'] +
        $moodImprovement     * ANALYTICS_WEIGHTS['mood_improvement'] +
        $ritualConsistency   * ANALYTICS_WEIGHTS['ritual_consistency'] +
        $btScore             * ANALYTICS_WEIGHTS['memory_breakthroughs'] +
        $recoveryScore       * ANALYTICS_WEIGHTS['recovery_speed']
    ) * 100;

    return [
        'computed_at'      => $today,
        'overall_score'    => round($overall),
        'mood_avg_7d'      => $avg7,
        'mood_avg_30d'     => $avg30,
        'mood_delta'       => $delta,
        'mood_trend'       => $delta === null ? 'unknown' : ($delta > 0.3 ? 'improving' : ($delta < -0.3 ? 'declining' : 'stable')),
        'checkin_streak'   => $checkinStreak,
        'ritual_streak'    => $streaks['current'],
        'ritual_streak_best'=> $streaks['longest'],
        'breakthroughs_30d'=> $breakthroughs,
        'recovery_speed'   => $recoverySpeed,
        'journal_entries'  => $journals,
    ];
}

function _analytics_latest_snapshot(int $userId): ?array
{
    $today = date('Y-m-d');
    $rows  = db_query(
        "SELECT * FROM growth_snapshots WHERE user_id=? AND date=?",
        [$userId, $today]
    );
    return $rows[0] ?? null;
}

function _analytics_maybe_award_milestones(int $userId, array $data): void
{
    // Mood improvement
    if (($data['mood_delta'] ?? 0) > 1.0) {
        _milestone_award($userId, 'mood_high', 'Significant Mood Improvement 📈',
            'Your average mood improved by more than 1 point this month.', $data['mood_delta']);
    }
    // High overall score
    if ($data['overall_score'] >= 80) {
        _milestone_award($userId, 'growth_score', 'Growth Score: ' . $data['overall_score'] . '/100 🌟',
            'Your emotional growth score reached ' . $data['overall_score'] . ' this month.', $data['overall_score']);
    }
    // First journal
    if ($data['journal_entries'] === 1) {
        _milestone_award($userId, 'first_ritual', 'First Voice Journal Entry 🎙️',
            'You recorded your first voice journal — a powerful step.', 1);
    }
}

function _reminder_get_user_patterns(int $userId): array
{
    // Last activity
    $lastMood = db_query(
        "SELECT MAX(created_at) as last_at FROM mood_logs WHERE user_id=?",
        [$userId]
    );
    $lastAt = $lastMood[0]['last_at'] ?? null;
    $daysInactive = $lastAt ? (int)floor((time() - strtotime($lastAt)) / 86400) : 999;

    // Dominant emotional state
    $states = db_query(
        "SELECT emotional_state, COUNT(*) as cnt FROM mood_logs WHERE user_id=? AND logged_at >= ?
         GROUP BY emotional_state ORDER BY cnt DESC LIMIT 1",
        [$userId, date('Y-m-d', strtotime('-7 days'))]
    );
    $dominant = $states[0]['emotional_state'] ?? 'neutral';

    return [
        'days_inactive'  => $daysInactive,
        'dominant_state' => $dominant,
        'last_activity'  => $lastAt,
    ];
}

function _reminder_inactivity_message(array $patterns): string
{
    $state = $patterns['dominant_state'] ?? 'neutral';
    $msgs  = [
        'burnout'      => "You've been quiet lately — your wellbeing matters. Come back when you're ready. 💛",
        'anxiety_high' => "Just checking in. Sometimes the hardest step is the first one back. 🌿",
        'low'          => "We've missed you. Even a quick check-in can make a difference today.",
        'neutral'      => "It's been a little while — how are you doing? Your coach is here. 🌟",
    ];
    return $msgs[$state] ?? $msgs['neutral'];
}

function _reminder_send(array $r): bool
{
    $coachName = $r['coach_name'] ?? 'your coach';
    $name      = $r['name']       ?? 'there';
    $email     = $r['email']      ?? '';

    if (!$email) return false;

    $templates = [
        'ritual_morning'  => [
            'subject' => "🌅 Good morning, {name} — your morning ritual is waiting",
            'body'    => "<p>Hi {name},</p><p>Starting the day with a few intentional moments can shift everything. Your morning check-in is ready whenever you are.</p><p><a href='".SITE_URL."/app.php' style='background:#6366f1;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none'>Begin Morning Ritual</a></p><p style='color:#888'>— {coach}</p>",
        ],
        'ritual_evening'  => [
            'subject' => "🌙 {name}, take a moment to close the day",
            'body'    => "<p>Hi {name},</p><p>Before the day slips away — a few minutes of reflection can help you rest easier tonight.</p><p><a href='".SITE_URL."/app.php' style='background:#6366f1;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none'>Evening Reflection</a></p><p style='color:#888'>— {coach}</p>",
        ],
        'inactivity'      => [
            'subject' => "💛 We've been thinking about you, {name}",
            'body'    => "<p>Hi {name},</p><p>It's been a little while. No pressure — just wanted to let you know your space here is still yours, whenever you need it.</p><p><a href='".SITE_URL."/app.php' style='background:#6366f1;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none'>Come Back</a></p><p style='color:#888'>— {coach}</p>",
        ],
        'stress_support'  => [
            'subject' => "🌿 A gentle check-in from {coach}",
            'body'    => "<p>Hi {name},</p><p>You've had some heavy moments lately. Even just a few minutes of grounding can help. I'm here whenever you're ready.</p><p><a href='".SITE_URL."/app.php' style='background:#6366f1;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none'>Talk to {coach}</a></p><p style='color:#888'>— {coach}</p>",
        ],
        'proactive_support' => [
            'subject' => "🌿 A quick note from {coach}",
            'body'    => "<div style='font-style:italic;font-size:18px;margin-bottom:20px;border-left:4px solid #c5a572;padding-left:20px'>I've been sensing you might be carrying a lot lately. Please remember that resting is also a form of progress. I'm here when you're ready to exhale.</div><p><a href='".SITE_URL."/app.php' style='background:#c5a572;color:#1a1008;padding:12px 30px;border-radius:50px;text-decoration:none;font-weight:600'>Check in with {coach} →</a></p>",
        ],
    ];

    $tpl = $templates[$r['reminder_type']] ?? null;
    if (!$tpl) return false;

    $subject = str_replace(['{name}', '{coach}'], [$name, $coachName], $tpl['subject']);
    $body    = str_replace(['{name}', '{coach}'], [$name, $coachName], $tpl['body']);

    return send_email($email, $subject, email_layout($body, $subject));
}
