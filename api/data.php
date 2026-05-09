<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/memory.php';
require_login();
check_maintenance(true);

/**
 * Call Claude to compress old memory entries into one long-term summary.
 * Returns a JSON string {"summary":"...","themes":["..."]} or null on failure.
 */
/**
 * Call the AI router to compress old memory entries into one long-term summary.
 * Returns a JSON string {"summary":"...","themes":["..."]} or null on failure.
 */
function compress_memories_via_ai(string $block): ?string {
    require_once dirname(__DIR__) . '/providers/router.php';
    $system = 'You are a memory compression assistant. Compress the session notes into a SINGLE concise long-term summary that preserves the most important patterns, recurring themes, and meaningful personal details. Respond ONLY with valid JSON — no preamble, no markdown: {"summary":"2-3 sentence narrative summary","themes":["theme1","theme2","theme3"]}';
    $messages = [['role' => 'user', 'content' => "Compress these session memories:\n\n{$block}"]];
    
    $text = route_ai_request_sync($messages, $system, 400);
    if (!$text) return null;
    
    $text = trim(preg_replace('/```json|```/', '', $text));
    $parsed = json_decode($text, true);
    return (is_array($parsed) && !empty($parsed['summary'])) ? $text : null;
}


header('Content-Type: application/json');
$user = current_user();
$uid  = $user['id'];
$act  = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
}

switch ($act) {

    // ── MOOD ──────────────────────────────────────────────────────────────
    case 'log_mood':
        $score = (int)($data['score'] ?? 0);
        $label = $data['label'] ?? '';
        $emoji = $data['emoji'] ?? '';
        $notes = trim($data['notes'] ?? '');
        $date  = date('Y-m-d');
        // Upsert today's mood
        db_run("DELETE FROM mood_logs WHERE user_id=? AND logged_date=?", [$uid, $date]);
        db_run("INSERT INTO mood_logs (user_id, score, label, emoji, notes, logged_date) VALUES (?, ?, ?, ?, ?, ?)",
            [$uid, $score, $label, $emoji, $notes, $date]);
        echo json_encode(['ok' => true]);
        break;

    case 'get_moods':
        $rows = db_query("SELECT * FROM mood_logs WHERE user_id=? ORDER BY logged_date DESC LIMIT 60", [$uid]);
        echo json_encode(['ok' => true, 'moods' => $rows]);
        break;

    // Advance the program by one day (called after user completes today's prompt).
    // Returns new program_day and whether the program just completed.
    case 'advance_program':
        $cp = db_one("SELECT program_day, purpose FROM coach_profiles WHERE user_id=?", [$uid]);
        if (!$cp) { echo json_encode(['ok'=>false,'error'=>'No profile']); break; }

        // Program lengths mirror the frontend PROGRAMS constant
        $lengths = ['emotional'=>14,'anxiety'=>14,'growth'=>14,'social'=>14];
        $purpose = $cp['purpose'] ?? 'emotional';
        $total   = $lengths[$purpose] ?? 14;
        $current = (int)$cp['program_day'];
        $next    = $current + 1;
        $completed = ($next >= $total);

        db_run(
            "UPDATE coach_profiles SET program_day=?, program_completed_at=" .
            ($completed ? "datetime('now')" : "NULL") . " WHERE user_id=?",
            [$completed ? 0 : $next, $uid]   // restart on completion
        );
        echo json_encode(['ok'=>true,'program_day'=>$completed?0:$next,'completed'=>$completed,'total'=>$total]);
        break;
    case 'save_memory':
        $summary = $data['summary'] ?? '';
        $themes  = json_encode($data['themes'] ?? []);
        db_run("INSERT INTO coach_memory (user_id, summary, themes) VALUES (?, ?, ?)", [$uid, $summary, $themes]);

        // Phase 10 — Integrate with Advanced Memory & Evolution
        if (get_setting('memory_enabled', '1') === '1') {
            memory_store_episode($uid, $summary, 'session', [
                'tags' => $data['themes'] ?? []
            ]);
        }

        // When we reach 20 entries, compress the oldest 15 into one long-term
        // summary so context is never permanently lost.
        $total = db_count('coach_memory', 'user_id=?', [$uid]);
        if ($total >= 20) {
            // Fetch oldest 15
            $old = db_query(
                "SELECT id, session_date, summary, themes FROM coach_memory
                  WHERE user_id=? ORDER BY id ASC LIMIT 15",
                [$uid]
            );
            if (count($old) >= 5) {
                // Build a compact text block and request Claude to compress it
                $block = implode("\n", array_map(
                    fn($m) => "{$m['session_date']}: {$m['summary']} [" . implode(', ', json_decode($m['themes'] ?? '[]', true)) . "]",
                    $old
                ));
                $oldIds = implode(',', array_column($old, 'id'));

                // Call the internal AI endpoint for compression
                $compressedJson = compress_memories_via_ai($block);
                if ($compressedJson) {
                    $p = json_decode($compressedJson, true);
                    if (!empty($p['summary'])) {
                        // Delete the 15 old entries
                        db_run("DELETE FROM coach_memory WHERE user_id=? AND id IN ($oldIds)", [$uid]);
                        // Insert one compressed long-term memory row
                        db_run(
                            "INSERT INTO coach_memory (user_id, summary, themes, session_date) VALUES (?, ?, ?, ?)",
                            [$uid, '[Long-term] ' . $p['summary'], json_encode($p['themes'] ?? []), date('Y-m-d')]
                        );
                    }
                }

                if (!$compressedJson) {
                    // Fallback: just drop the oldest 10 if Claude compression fails
                    $fallbackIds = implode(',', array_column(array_slice($old, 0, 10), 'id'));
                    db_run("DELETE FROM coach_memory WHERE user_id=? AND id IN ($fallbackIds)", [$uid]);
                }
            }
        }

        echo json_encode(['ok' => true]);
        break;

    case 'get_memory':
        $rows = db_query("SELECT * FROM coach_memory WHERE user_id=? ORDER BY id DESC LIMIT 20", [$uid]);
        foreach ($rows as &$r) $r['themes'] = json_decode($r['themes'] ?? '[]', true);
        echo json_encode(['ok' => true, 'memory' => array_reverse($rows)]);
        break;

    // ── COACH PROFILE ─────────────────────────────────────────────────────
    case 'save_profile':
        // Only allow safe fields from the client — never trust streak/last_date from client
        $fields = ['purpose', 'tone', 'challenge', 'coach_name', 'program_day'];
        $set = []; $vals = [];
        foreach ($fields as $f) {
            if (isset($data[$f])) { $set[] = "$f=?"; $vals[] = $data[$f]; }
        }

        // ── SERVER-SIDE STREAK VALIDATION ────────────────────────────────
        // Compute streak here so the client can't manipulate it by sending
        // arbitrary day_streak / last_date values.
        $existing = db_one("SELECT id, last_date, day_streak FROM coach_profiles WHERE user_id=?", [$uid]);
        $todayStr = date('Y-m-d');
        if ($existing) {
            $lastDate  = $existing['last_date'] ?? '';
            $prevStreak = (int)($existing['day_streak'] ?? 0);
            if ($lastDate === $todayStr) {
                // Already checked in today — keep streak as-is
                $newStreak = $prevStreak ?: 1;
            } elseif ($lastDate === date('Y-m-d', strtotime('-1 day'))) {
                // Consecutive day — increment
                $newStreak = $prevStreak + 1;
            } else {
                // Missed a day (or first real checkin) — reset to 1
                $newStreak = 1;
            }
            $set[]  = "day_streak=?";  $vals[] = $newStreak;
            $set[]  = "last_date=?";   $vals[] = $todayStr;
        }

        if ($set) {
            $vals[] = $uid;
            if ($existing) {
                db_run("UPDATE coach_profiles SET " . implode(',', $set) . ",updated_at=datetime('now') WHERE user_id=?", $vals);
            } else {
                // First save — insert with streak=1
                $cols = array_map(fn($s) => explode('=', $s)[0], $set);
                db_run(
                    "INSERT INTO coach_profiles (user_id," . implode(',', $cols) . ") VALUES (?" . str_repeat(',?', count($cols)) . ")",
                    array_merge([$uid], array_slice($vals, 0, -1))
                );
            }
        }
        // Return new streak so the frontend stays in sync without trusting its own value
        $updated = db_one("SELECT day_streak FROM coach_profiles WHERE user_id=?", [$uid]);
        echo json_encode(['ok' => true, 'day_streak' => (int)($updated['day_streak'] ?? 1)]);
        break;

    case 'get_profile':
        $profile = db_one("SELECT * FROM coach_profiles WHERE user_id=?", [$uid]);
        echo json_encode(['ok' => true, 'profile' => $profile]);
        break;

    // ── CHAT SESSIONS ─────────────────────────────────────────────────────
    // Save (upsert) today's session messages.
    // Body: { messages: [{role, content, ts}] }
    case 'save_session':
        $msgs  = $data['messages'] ?? [];
        $today = date('Y-m-d');
        $json  = json_encode($msgs, JSON_UNESCAPED_UNICODE);
        $count = count($msgs);
        db_run(
            "INSERT INTO chat_sessions (user_id, session_date, messages, message_count, updated_at)
             VALUES (?, ?, ?, ?, datetime('now'))
             ON CONFLICT(user_id, session_date)
             DO UPDATE SET messages=excluded.messages,
                           message_count=excluded.message_count,
                           updated_at=excluded.updated_at",
            [$uid, $today, $json, $count]
        );
        echo json_encode(['ok' => true]);
        break;

    // Load today's session — returns messages array (empty if none yet).
    case 'get_session':
        $today = date('Y-m-d');
        $row   = db_one(
            "SELECT messages FROM chat_sessions WHERE user_id=? AND session_date=?",
            [$uid, $today]
        );
        $msgs = $row ? (json_decode($row['messages'], true) ?? []) : [];
        echo json_encode(['ok' => true, 'messages' => $msgs, 'is_new' => empty($msgs)]);
        break;

    // Return a list of past session dates + message counts (for a history view).
    // Optionally accepts ?date=YYYY-MM-DD to load a specific session.
    case 'get_session_history':
        if (!empty($data['date'])) {
            $row  = db_one(
                "SELECT session_date, messages, message_count FROM chat_sessions
                  WHERE user_id=? AND session_date=? LIMIT 1",
                [$uid, $data['date']]
            );
            if (!$row) { echo json_encode(['ok' => true, 'session' => null]); break; }
            $row['messages'] = json_decode($row['messages'] ?? '[]', true);
            echo json_encode(['ok' => true, 'session' => $row]);
        } else {
            $rows = db_query(
                "SELECT session_date, message_count FROM chat_sessions
                  WHERE user_id=? AND message_count > 0
                  ORDER BY session_date DESC LIMIT 30",
                [$uid]
            );
            echo json_encode(['ok' => true, 'history' => $rows]);
        }
        break;

    // ── EMOTIONAL INTELLIGENCE (Phase 2) ─────────────────────────────────

    // Get emotional scores + nudge for dashboard display
    case 'get_emotional_state':
        require_once dirname(__DIR__) . '/includes/prompt_engine.php';
        $scores = build_emotional_scores($uid);
        $nudge  = null;
        if (get_setting('nudge_enabled', '1') === '1') {
            $nudge = get_emotional_nudge($uid);
        }
        echo json_encode(['ok' => true, 'scores' => $scores, 'nudge' => $nudge]);
        break;

    // Log an emotion event (called from ai.php or from client on mood log)
    case 'log_emotion':
        if (!isset($data['state'])) { echo json_encode(['ok' => false, 'error' => 'state required']); break; }
        $state      = $data['state'];
        $score      = (float)($data['score'] ?? 0.3);
        $indicators = json_encode($data['indicators'] ?? []);
        $source     = $data['source'] ?? 'chat';
        db_run(
            "INSERT INTO emotion_events (user_id, state, score, indicators, source) VALUES (?, ?, ?, ?, ?)",
            [$uid, $state, $score, $indicators, $source]
        );
        echo json_encode(['ok' => true]);
        break;

    // Get recent emotion history for analytics
    case 'get_emotion_history':
        $rows = db_query(
            "SELECT state, score, source, created_at FROM emotion_events
              WHERE user_id=? ORDER BY created_at DESC LIMIT 30",
            [$uid]
        );
        echo json_encode(['ok' => true, 'emotions' => $rows]);
        break;

    // ── LONGITUDINAL LIFE INTELLIGENCE (Phase 10) ────────────────────────
    case 'get_life_intelligence':
        require_once dirname(__DIR__) . '/providers/router.php';
        // Fetch last 20 episodes to analyze patterns
        $episodes = db_query("SELECT summary, themes, created_at FROM memory_episodes WHERE user_id=? ORDER BY created_at DESC LIMIT 20", [$uid]);
        if (empty($episodes)) {
            echo json_encode(['ok' => true, 'intelligence' => null]);
            break;
        }

        $block = "";
        foreach ($episodes as $e) {
            $date = date('M j', strtotime($e['created_at']));
            $block .= "Session ({$date}): {$e['summary']}\n";
        }

        $system = "You are an expert longitudinal life intelligence analyst for Solen.
                   Your task is to analyze the user's session history and identify:
                   1. Current Life Phase (e.g. 'Seeking Clarity', 'Navigating Burnout', 'Steady Growth')
                   2. Emotional Evolution (how their mood/energy has shifted)
                   3. Long-term Growth Patterns (recurring breakthroughs or recurring blocks)
                   Respond ONLY with valid JSON:
                   {
                     \"life_phase\": \"...\",
                     \"evolution\": \"...\",
                     \"patterns\": [\"...\", \"...\"],
                     \"insight\": \"A single, powerful observation about their growth journey.\"
                   }";

        $messages = [['role' => 'user', 'content' => "Analyze my journey based on these memories:\n\n{$block}"]];
        $jsonStr = route_ai_request_sync($messages, $system, 600);
        
        $intel = json_decode(trim(preg_replace('/```json|```/', '', $jsonStr)), true);
        echo json_encode(['ok' => true, 'intelligence' => $intel]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}
