<?php
/**
 * /includes/predictive.php — Solen Predictive Intelligence (Phase 10)
 *
 * Analyzes mood logs and memory episodes to predict burnout, isolation, and emotional decline.
 */

function predictive_analyze_user(int $userId): array {
    $insights = [];
    $riskLevel = 0; // 0 to 100

    // 1. Analyze Mood Trends (Last 14 days)
    $moods = db_query(
        "SELECT score, logged_date FROM mood_logs WHERE user_id=? ORDER BY logged_date DESC LIMIT 14",
        [$userId]
    );

    if (count($moods) >= 3) {
        $scores = array_column($moods, 'score');
        
        // Detect Decline
        $isDeclining = true;
        for ($i = 0; $i < count($scores) - 1; $i++) {
            if ($scores[$i] > $scores[$i+1]) { $isDeclining = false; break; }
        }
        
        if ($isDeclining && count($scores) >= 4) {
            $insights[] = [
                'type' => 'emotional_decline',
                'severity' => 'medium',
                'message' => 'Steady decline in mood observed over 4+ days.'
            ];
            $riskLevel += 30;
        }

        // Detect Burnout (Low scores + Work themes)
        $avgRecent = array_sum(array_slice($scores, 0, 5)) / 5;
        if ($avgRecent < 4) {
            $workMemories = db_count('memory_episodes', "user_id=? AND (summary LIKE '%work%' OR summary LIKE '%exhausted%' OR summary LIKE '%tired%') AND created_at > datetime('now','-7 days')", [$userId]);
            if ($workMemories > 2) {
                $insights[] = [
                    'type' => 'burnout_risk',
                    'severity' => 'high',
                    'message' => 'High risk of burnout detected (low mood + exhaustion themes).'
                ];
                $riskLevel += 50;
            }
        }
    }

    // 2. Analyze Interaction/Isolation
    $lastInteraction = db_one("SELECT created_at FROM memory_episodes WHERE user_id=? ORDER BY created_at DESC LIMIT 1", [$userId]);
    if ($lastInteraction) {
        $daysSince = (time() - strtotime($lastInteraction['created_at'])) / 86400;
        if ($daysSince > 4) {
            $isolationMemories = db_count('memory_episodes', "user_id=? AND (summary LIKE '%alone%' OR summary LIKE '%lonely%' OR summary LIKE '%nobody%') AND created_at > datetime('now','-14 days')", [$userId]);
            if ($isolationMemories > 1) {
                $insights[] = [
                    'type' => 'isolation_pattern',
                    'severity' => 'medium',
                    'message' => 'Isolation pattern detected (prolonged silence + loneliness themes).'
                ];
                $riskLevel += 40;
            }
        }
    }

    return [
        'risk_score' => min(100, $riskLevel),
        'insights'   => $insights,
        'summary'    => count($insights) ? "User is showing signs of " . implode(' and ', array_column($insights, 'type')) : "User state is stable."
    ];
}

/**
 * World-Class Proactive Support:
 * Run nightly via cron. Detects high-risk users and sends a gentle, proactive email
 * from their coach before they even ask for help.
 */
function predictive_run_proactive_check(): int {
    $users = db_query("SELECT id, name, email FROM users WHERE plan != 'free'"); // Premium feature
    $triggered = 0;

    foreach ($users as $u) {
        $userId = (int)$u['id'];
        $analysis = predictive_analyze_user($userId);

        if ($analysis['risk_score'] >= 70) {
            // Check if we already sent an intervention in the last 7 days to avoid spam
            $recent = db_one("SELECT id FROM reminder_schedules WHERE user_id=? AND reminder_type='proactive_support' AND created_at > datetime('now','-7 days')", [$userId]);
            if (!$recent) {
                $insight = $analysis['insights'][0] ?? null;
                if ($insight) {
                    $msg = _predictive_get_support_message($u['name'], $insight['type']);
                    
                    // Schedule for immediate delivery
                    db_run(
                        "INSERT INTO reminder_schedules (user_id, reminder_type, channel, scheduled_at) 
                         VALUES (?, 'proactive_support', 'email', datetime('now'))",
                        [$userId]
                    );
                    $triggered++;
                }
            }
        }
    }
    return $triggered;
}

function _predictive_get_support_message(string $name, string $type): string {
    $msgs = [
        'burnout_risk'      => "I've been sensing you might be carrying a lot lately. Please remember that resting is also a form of progress. I'm here when you're ready to exhale.",
        'emotional_decline' => "Just wanted to send a gentle note to say I'm thinking of you. You don't have to carry everything alone today.",
        'isolation_pattern' => "It's been a little while, and I wanted to make sure you're feeling okay. Your wellness journey is a marathon, not a sprint. I'm here.",
    ];
    return $msgs[$type] ?? "Just checking in on you. How is your heart doing today?";
}
