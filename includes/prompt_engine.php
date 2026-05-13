<?php
/**
 * Solen Central Prompt Engine — Phase 1 #5 + Phase 2
 *
 * build_system_prompt($profile, $memory, $emotionalState) → string
 * detect_emotion($text) → array {state, score, indicators}
 * get_emotional_nudge($userId) → string|null
 * build_emotional_scores($userId) → array
 */

// ── PROMPT CONSTANTS ──────────────────────────────────────────────────────

const SOLEN_PURPOSE_PROMPTS = [
    'emotional' => "You are a deeply empathetic emotional wellness coach. Your primary role is to make the user feel genuinely heard and understood. Listen actively, reflect back feelings with warmth, and never rush to solutions — presence and validation come first. Use open-ended questions to deepen emotional exploration.",
    'anxiety'   => "You are a calm, grounding anxiety and stress management coach. You integrate evidence-based CBT techniques and mindfulness naturally — breathing prompts, cognitive reframing, grounding exercises, and gentle psychoeducation as the moment calls for them. Never catastrophise; always steady.",
    'growth'    => "You are an insightful personal growth coach. Ask powerful reflective questions that challenge limiting beliefs gently. Celebrate wins with genuine enthusiasm. Help the user build momentum through small, concrete next steps. Mirror their language and ambition back to them.",
    'social'    => "You are a warm, patient social confidence coach. Help users understand social dynamics, build conversational skills, and reframe social anxiety through practice and compassionate feedback. Role-play scenarios when helpful.",
];

const SOLEN_TONE_STYLES = [
    'warm'       => "Your tone is warm, gentle, and deeply caring. Use affirming, soft language. Never rush the user. Create psychological safety above all.",
    'direct'     => "Your tone is honest, clear, and respectfully direct. Skip filler words. Respect the user's intelligence. Be concise but never cold.",
    'playful'    => "Your tone is light, uplifting, and occasionally gently humorous — but always emotionally sensitive. Lift the mood when appropriate without dismissing pain.",
    'wise'       => "Your tone is calm, measured, and thoughtful — like a seasoned therapist or wise mentor. Unhurried. Allow silence and depth.",
    'gentle'     => "Your tone is soft and supportive, prioritizing comfort.",
    'reflective' => "Your tone is curious and analytical, helping the user see patterns.",
    'stoic'      => "Your tone is grounded, steady, and resilient. Focus on what can be controlled.",
];

// Relationship Progression Levels (Phase 10)
const SOLEN_RELATIONSHIP_LEVELS = [
    1 => "RELATIONSHIP: STRANGER. The user is new to you. Be professional, welcoming, and build basic safety. Don't be over-familiar yet.",
    2 => "RELATIONSHIP: ACQUAINTANCE. The user has shared some thoughts. Acknowledge past interactions gently. Show you remember the basics.",
    3 => "RELATIONSHIP: TRUSTED COMPANION. You have a solid bond. You can be more direct, use 'we' language, and explore deeper vulnerabilities together.",
    4 => "RELATIONSHIP: CORE SUPPORT. You are a primary emotional anchor for this user. You can challenge them more, be deeply personal, and speak with high intimacy and warmth.",
];

const SOLEN_CHALLENGE_CONTEXT = [
    'overwhelm'  => "This person is struggling with feeling overwhelmed by too much at once. Help them find one small anchor — one thing they can do or release today.",
    'loneliness' => "This person is experiencing deep loneliness and disconnection. Warmth and genuine presence are the most important things you can offer right now.",
    'selfworth'  => "This person struggles with self-doubt and low self-worth. Help them see themselves with clarity and compassion. Celebrate specifics, not generalities.",
    'direction'  => "This person lacks clarity about what they want or where they're going. Help them explore with curiosity — not pressure. Possibilities, not prescriptions.",
];

// Emotional state tone modifiers (Phase 2)
const SOLEN_EMOTIONAL_TONE_MODS = [
    'crisis'       => "IMPORTANT: The user is showing signs of crisis or severe distress. Slow down completely. Lead with grounding. Acknowledge before anything else. Do not offer solutions yet. Gently ask one simple question about their immediate safety.",
    'high_distress'=> "The user appears to be in significant emotional pain right now. Drop any agenda. Be fully present. Prioritise emotional acknowledgement over coaching.",
    'burnout'      => "The user shows signs of burnout — exhaustion and emotional depletion. Speak gently. Validate tiredness. Avoid adding any pressure. Help them feel permission to rest.",
    'anxiety_high' => "The user's anxiety is elevated right now. Use a calm, slow pacing. Include grounding language. Avoid overwhelming them with questions or information.",
    'low'          => "The user seems to be in a low emotional state. Be extra gentle, extra warm. Reflect before redirecting.",
];

const SOLEN_SAFETY_INSTRUCTIONS = "If the user expresses thoughts of self-harm, suicide, or harming others: acknowledge their pain with deep compassion, do not minimise or over-react, gently introduce the idea that professional support exists, and provide the crisis line (988 in US, or local equivalent) naturally within your response. Your first job is to make them feel safe and heard.";

// Grounding techniques for high distress/panic (Phase 7)
const SOLEN_GROUNDING_PROMPT = "The user is currently experiencing high distress or panic. Help them ground themselves immediately. Use a gentle, steady pace. Choose ONE of these techniques to guide them through:
1. 5-4-3-2-1 Technique: 5 things you see, 4 you can touch, 3 you hear, 2 you smell, 1 you can taste.
2. Box Breathing: Inhale 4s, Hold 4s, Exhale 4s, Hold 4s.
3. Anchor Point: Find one physical object in the room and describe its texture and temperature in detail.
Keep your response brief and focused on the exercise.";

// ── MAIN BUILDER ──────────────────────────────────────────────────────────

/**
 * Build the full system prompt for a session.
 *
 * @param array  $profile       coach_profiles row (purpose, tone, challenge, coach_name)
 * @param array  $memory        array of coach_memory rows (legacy — still supported)
 * @param string $emotionalState current detected emotional state (see SOLEN_EMOTIONAL_TONE_MODS keys)
 * @param array  $opts          extra options: program_day, program_name, session_number,
 *                              user_id (int, for Phase 3 semantic memory),
 *                              current_text (string, user's latest message for semantic lookup)
 */
function build_system_prompt(array $profile, array $memory = [], string $emotionalState = '', array $opts = []): string {
    $purpose   = $profile['purpose']    ?? 'emotional';
    $tone      = $profile['tone']       ?? 'warm';
    $challenge = $profile['challenge']  ?? '';
    $coachName = $profile['coach_name'] ?? 'your coach';

    // Core identity
    $parts = [];
    $parts[] = SOLEN_PURPOSE_PROMPTS[$purpose] ?? SOLEN_PURPOSE_PROMPTS['emotional'];
    $parts[] = SOLEN_TONE_STYLES[$tone] ?? SOLEN_TONE_STYLES['warm'];
    if ($challenge && isset(SOLEN_CHALLENGE_CONTEXT[$challenge])) {
        $parts[] = SOLEN_CHALLENGE_CONTEXT[$challenge];
    }

    // Emotional state modifier (Phase 2)
    if ($emotionalState && isset(SOLEN_EMOTIONAL_TONE_MODS[$emotionalState])) {
        $parts[] = SOLEN_EMOTIONAL_TONE_MODS[$emotionalState];
    }

    // Phase 10: Relationship & Personality Evolution
    $relLevel = (int)($profile['relationship_level'] ?? 1);
    $parts[]  = SOLEN_RELATIONSHIP_LEVELS[$relLevel] ?? SOLEN_RELATIONSHIP_LEVELS[1];
    
    $pStyle = $profile['personality_style'] ?? 'gentle';
    if (isset(SOLEN_TONE_STYLES[$pStyle])) {
        $parts[] = "ADAPTED STYLE: " . SOLEN_TONE_STYLES[$pStyle];
    }

    // Predictive insights (Phase 10)
    if (!empty($opts['predictive_insight'])) {
        $parts[] = "CRITICAL PREDICTIVE INSIGHT: " . $opts['predictive_insight'];
    }

    // Coach identity
    $parts[] = "Your name is {$coachName}. You are a professional wellness coach — not a chatbot. You have genuine care, continuity, and wisdom. Keep responses conversational: 2–4 sentences unless the user clearly needs more. No bullet points in emotional conversations. End with one thoughtful, open-ended question.";

    // Safety
    $parts[] = SOLEN_SAFETY_INSTRUCTIONS;

    // Memory injection — Phase 3 (semantic + episodic + emotional, with legacy fallback)
    $memoryBlock = '';
    $userId = (int)($opts['user_id'] ?? 0);
    $fastMode = (bool)($opts['fast_mode'] ?? false);
    if ($userId && function_exists('memory_build_context') && get_setting('memory_enabled', '1') === '1') {
        $memoryBlock = memory_build_context($userId, $opts['current_text'] ?? '', $fastMode);
    }

    // Legacy fallback: coach_memory rows (Phase 1 simple summaries)
    if (!$memoryBlock && !empty($memory)) {
        $memLines = [];
        foreach (array_slice($memory, -6) as $m) {
            $summary = $m['summary'] ?? '';
            $themes  = is_array($m['themes']) ? implode(', ', $m['themes']) : ($m['themes'] ?? '');
            $date    = $m['session_date'] ?? '';
            if ($summary) {
                $memLines[] = "- {$date}: {$summary}" . ($themes ? " [themes: {$themes}]" : '');
            }
        }
        if ($memLines) {
            $memoryBlock = "WHAT YOU REMEMBER ABOUT THIS PERSON:\n" . implode("\n", $memLines)
                         . "\n\nUse this context naturally — reference it when it deepens the conversation, but never recite it robotically.";
        }
    }

    if ($memoryBlock) {
        $parts[] = $memoryBlock;
    }

    // Program context
    if (!empty($opts['program_day']) && !empty($opts['program_name'])) {
        $day  = (int)$opts['program_day'] + 1;
        $name = $opts['program_name'];
        $parts[] = "You are currently on Day {$day} of the user's '{$name}' program. Keep that context in mind as you guide today's reflection.";
    }

    return implode("\n\n", $parts);
}

// ── EMOTION DETECTION ENGINE ──────────────────────────────────────────────
// Phase 2 #1 — lightweight rule-based detection (no external API cost)
// with optional LLM-backed classification for ambiguous cases.

/**
 * Detect emotional state from user message text.
 *
 * Returns:
 *   state      string  'crisis'|'high_distress'|'burnout'|'anxiety_high'|'low'|'neutral'|'positive'
 *   score      float   0.0 (positive) → 1.0 (crisis)
 *   indicators array   matched keywords/patterns
 */
function detect_emotion(string $text): array {
    $text_lower = mb_strtolower($text);
    $indicators = [];

    // ── CRISIS (highest priority) ──────────────────────────────────────
    $crisis_patterns = [
        '/\b(suicid|kill myself|end my life|want to die|don\'t want to live|no reason to live|better off (without me|dead)|self[- ]?harm|hurt myself|cutting myself)\b/i'
    ];
    foreach ($crisis_patterns as $p) {
        if (preg_match($p, $text)) {
            $indicators[] = 'crisis_language';
            return ['state' => 'crisis', 'score' => 1.0, 'indicators' => $indicators];
        }
    }

    // ── EMOTIONAL COLLAPSE (Phase 7) ──────────────────────────────────
    $collapse_words = ['can\'t go on', 'giving up', 'nothing matters anymore', 'it\'s over', 'no way out', 'trapped forever', 'done with everything'];
    $collapse_count = 0;
    foreach ($collapse_words as $w) {
        if (str_contains($text_lower, $w)) { $collapse_count++; $indicators[] = "collapse_{$w}"; }
    }
    if ($collapse_count >= 1) {
        return ['state' => 'crisis', 'score' => 0.9, 'indicators' => $indicators];
    }

    // ── HIGH DISTRESS ─────────────────────────────────────────────────
    $distress_words = ['devastated', 'hopeless', 'worthless', 'broken', 'can\'t cope', 'falling apart',
                       'not okay', 'hate myself', 'exhausted', 'nothing matters', 'alone', 'unloved',
                       'trapped', 'numb', 'empty', 'pointless', 'shattered'];
    $distress_count = 0;
    foreach ($distress_words as $w) {
        if (str_contains($text_lower, $w)) { $distress_count++; $indicators[] = $w; }
    }
    if ($distress_count >= 2) {
        return ['state' => 'high_distress', 'score' => 0.8, 'indicators' => $indicators];
    }

    // ── BURNOUT ───────────────────────────────────────────────────────
    $burnout_words = ['burnt out', 'burned out', 'exhausted', 'drained', 'no energy', 'so tired',
                      'can\'t anymore', 'running on empty', 'depleted', 'too much'];
    $burnout_count = 0;
    foreach ($burnout_words as $w) {
        if (str_contains($text_lower, $w)) { $burnout_count++; $indicators[] = $w; }
    }
    if ($burnout_count >= 2) {
        return ['state' => 'burnout', 'score' => 0.7, 'indicators' => $indicators];
    }

    // ── ANXIETY ───────────────────────────────────────────────────────
    // ── PANIC / ACUTE ANXIETY (Phase 7) ─────────────────────────────────
    $panic_words = ['can\'t breathe', 'heart racing', 'chest tight', 'going crazy', 'going to die', 'panicking', 'panic attack', 'help me breathe', 'spiralling out of control'];
    $panic_count = 0;
    foreach ($panic_words as $w) {
        if (str_contains($text_lower, $w)) { $panic_count++; $indicators[] = "panic_{$w}"; }
    }
    if ($panic_count >= 1) {
        return ['state' => 'anxiety_high', 'score' => 0.75, 'indicators' => $indicators];
    }

    $anxiety_words = ['anxious', 'panicking', 'panic', 'worried', 'scared', 'terrified', 'overwhelmed',
                      'can\'t breathe', 'heart racing', 'spiraling', 'spiralling', 'freaking out',
                      'catastroph', 'worst case', 'dread'];
    $anxiety_count = 0;
    foreach ($anxiety_words as $w) {
        if (str_contains($text_lower, $w)) { $anxiety_count++; $indicators[] = $w; }
    }
    if ($anxiety_count >= 2) {
        return ['state' => 'anxiety_high', 'score' => 0.65, 'indicators' => $indicators];
    }

    // ── LOW ───────────────────────────────────────────────────────────
    $low_words = ['sad', 'down', 'low', 'unhappy', 'miserable', 'depressed', 'blue', 'gloomy',
                  'unmotivated', 'not feeling it', 'blah', 'meh', 'struggling'];
    $low_count = 0;
    foreach ($low_words as $w) {
        if (str_contains($text_lower, $w)) { $low_count++; $indicators[] = $w; }
    }
    if ($low_count >= 1 || $distress_count === 1) {
        return ['state' => 'low', 'score' => 0.4, 'indicators' => $indicators];
    }

    // ── POSITIVE ─────────────────────────────────────────────────────
    $positive_words = ['great', 'amazing', 'wonderful', 'happy', 'excited', 'grateful', 'thankful',
                       'hopeful', 'proud', 'confident', 'good day', 'better today'];
    foreach ($positive_words as $w) {
        if (str_contains($text_lower, $w)) { $indicators[] = $w; }
    }
    if (!empty($indicators)) {
        return ['state' => 'positive', 'score' => 0.1, 'indicators' => $indicators];
    }

    return ['state' => 'neutral', 'score' => 0.3, 'indicators' => []];
}

// ── EMOTIONAL SCORING SYSTEM ──────────────────────────────────────────────
// Phase 2 #3 — track burnout risk, stress trends, recovery

/**
 * Build emotional scores for a user based on recent mood logs + chat patterns.
 * Returns associative array of scores (0.0–1.0).
 */
function build_emotional_scores(int $userId): array {
    // Last 14 days of mood logs
    $moods = db_query(
        "SELECT score, logged_date FROM mood_logs WHERE user_id=? ORDER BY logged_date DESC LIMIT 14",
        [$userId]
    );

    if (empty($moods)) {
        return [
            'burnout_risk'       => 0.0,
            'emotional_decline'  => 0.0,
            'stress_trend'       => 0.0,
            'emotional_resilience' => 0.5,
            'recovery_progress'  => 0.5,
        ];
    }

    $scores = array_column($moods, 'score');
    $avg    = array_sum($scores) / count($scores);
    $recent = array_slice($scores, 0, 3);  // last 3
    $older  = array_slice($scores, 3, 7);  // previous week

    $recentAvg = $recent ? array_sum($recent) / count($recent) : $avg;
    $olderAvg  = $older  ? array_sum($older)  / count($older)  : $avg;

    // Burnout risk: consistently low scores
    $burnout_risk = max(0, min(1, (3.0 - $avg) / 2.5));

    // Emotional decline: recent worse than older
    $decline = ($olderAvg - $recentAvg) / 4.0;  // normalise
    $emotional_decline = max(0, min(1, $decline));

    // Stress trend: variance in scores (high variance = stress)
    $variance = 0;
    foreach ($scores as $s) $variance += pow($s - $avg, 2);
    $variance = count($scores) > 1 ? $variance / count($scores) : 0;
    $stress_trend = min(1, $variance / 4.0);

    // Resilience: consistent mid-to-high scores with recovery after lows
    $emotional_resilience = min(1, max(0, ($avg - 1.5) / 2.5));

    // Recovery: trending up recently
    $recovery = ($recentAvg - $olderAvg) / 4.0;
    $recovery_progress = max(0, min(1, 0.5 + $recovery));

    return [
        'burnout_risk'         => round($burnout_risk, 2),
        'emotional_decline'    => round($emotional_decline, 2),
        'stress_trend'         => round($stress_trend, 2),
        'emotional_resilience' => round($emotional_resilience, 2),
        'recovery_progress'    => round($recovery_progress, 2),
    ];
}

// ── SMART EMOTIONAL NUDGES ────────────────────────────────────────────────
// Phase 2 #4 — proactive, caring observations based on patterns

/**
 * Generate a personalised emotional nudge based on user patterns.
 * Returns a string (the nudge) or null if no meaningful nudge is available.
 */
function get_emotional_nudge(int $userId): ?string {
    $scores = build_emotional_scores($userId);
    $moods  = db_query(
        "SELECT score, logged_date FROM mood_logs WHERE user_id=? ORDER BY logged_date DESC LIMIT 30",
        [$userId]
    );

    if (count($moods) < 3) return null; // not enough data yet

    // Burnout nudge
    if ($scores['burnout_risk'] > 0.7) {
        return "You've been carrying a heavy load lately. Even small moments of rest matter — your energy isn't infinite, and that's okay.";
    }

    // Declining trend nudge
    if ($scores['emotional_decline'] > 0.6) {
        return "Your mood has dipped a bit over the past few days. What's been weighing on you most?";
    }

    // Recovery nudge
    if ($scores['recovery_progress'] > 0.7 && $scores['burnout_risk'] < 0.3) {
        return "You've been showing real resilience lately. It's worth acknowledging how far you've come.";
    }

    // Weekend pattern (check if lows cluster on weekdays)
    $weekdayScores = [];
    $weekendScores = [];
    foreach ($moods as $m) {
        $dow = (int)date('N', strtotime($m['logged_date']));
        if ($dow <= 5) $weekdayScores[] = $m['score'];
        else $weekendScores[] = $m['score'];
    }
    if (count($weekdayScores) >= 3 && count($weekendScores) >= 1) {
        $wdAvg = array_sum($weekdayScores) / count($weekdayScores);
        $weAvg = array_sum($weekendScores) / count($weekendScores);
        if ($weAvg - $wdAvg > 1.0) {
            return "Your mood tends to lift on weekends. Work or structure might be creating more stress than you realise — that's worth exploring.";
        }
    }

    // High stress trend
    if ($scores['stress_trend'] > 0.6) {
        return "Your emotional energy has been quite variable lately. Grounding yourself in a consistent daily rhythm — even something small — can help.";
    }

    return null;
}
