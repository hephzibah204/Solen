<?php
/**
 * Solen — Addiction Recovery Module
 *
 * Defines addiction categories, recovery-specific rituals, and prompt modifiers.
 * Included by retention.php, prompt_engine.php, and rituals.php.
 */

// ── ADDICTION CATEGORIES ────────────────────────────────────────────────────

const ADDICTION_CATEGORIES = [
    'substance' => [
        'label'       => 'Substance',
        'icon'        => '🧪',
        'description' => 'Alcohol, nicotine, prescription drugs, or recreational substances.',
        'color'       => '#f97316',
        'sub'         => ['alcohol', 'nicotine', 'cannabis', 'opioids', 'stimulants', 'other_substance'],
    ],
    'behavioural' => [
        'label'       => 'Behavioural',
        'icon'        => '📱',
        'description' => 'Social media, gaming, pornography, or gambling habits.',
        'color'       => '#8b5cf6',
        'sub'         => ['social_media', 'gaming', 'pornography', 'gambling', 'shopping'],
    ],
    'eating' => [
        'label'       => 'Eating & Body',
        'icon'        => '🍽️',
        'description' => 'Binge eating, restriction, emotional eating, or body image struggles.',
        'color'       => '#ec4899',
        'sub'         => ['binge_eating', 'restriction', 'emotional_eating', 'purging'],
    ],
    'work' => [
        'label'       => 'Work & Productivity',
        'icon'        => '💼',
        'description' => 'Workaholism, perfectionism, or compulsive busyness as avoidance.',
        'color'       => '#14b8a6',
        'sub'         => ['workaholism', 'perfectionism', 'compulsive_busyness'],
    ],
    'relationship' => [
        'label'       => 'Relationship',
        'icon'        => '💔',
        'description' => 'Codependency, love addiction, or unhealthy relationship patterns.',
        'color'       => '#f43f5e',
        'sub'         => ['codependency', 'love_addiction', 'people_pleasing'],
    ],
    'emotional' => [
        'label'       => 'Emotional Patterns',
        'icon'        => '🌀',
        'description' => 'Avoidance, rage cycles, self-harm patterns, or numbing behaviours.',
        'color'       => '#6366f1',
        'sub'         => ['avoidance', 'rage_cycles', 'emotional_numbing', 'self_harm_patterns'],
    ],
];

// ── RECOVERY RITUAL DEFAULTS ─────────────────────────────────────────────────

const RECOVERY_RITUAL_DEFAULTS = [
    'morning' => [
        [
            'key'          => 'recovery_intention',
            'label'        => 'Recovery Intention',
            'description'  => 'Set one positive intention for staying on your recovery path today.',
            'icon'         => '🌅',
            'duration_min' => 2,
        ],
        [
            'key'          => 'recovery_checkin',
            'label'        => 'Sobriety / Clarity Check-in',
            'description'  => 'How are you feeling in your recovery today? Rate your craving or urge level.',
            'icon'         => '🛡️',
            'duration_min' => 2,
        ],
    ],
    'evening' => [
        [
            'key'          => 'urge_log',
            'label'        => 'Urge Log',
            'description'  => 'Did any urges or triggers come up today? Naming them takes away their power.',
            'icon'         => '🌊',
            'duration_min' => 3,
        ],
        [
            'key'          => 'recovery_gratitude',
            'label'        => 'Recovery Gratitude',
            'description'  => 'Name one thing your clarity or sobriety gave you today.',
            'icon'         => '✨',
            'duration_min' => 2,
        ],
    ],
    'weekly' => [
        [
            'key'          => 'recovery_reflection',
            'label'        => 'Weekly Recovery Reflection',
            'description'  => 'Look back at this week\'s challenges and victories in your recovery.',
            'icon'         => '🌿',
            'duration_min' => 5,
        ],
        [
            'key'          => 'trigger_awareness',
            'label'        => 'Trigger Awareness Review',
            'description'  => 'What situations, emotions, or people triggered your urges this week?',
            'icon'         => '🔍',
            'duration_min' => 3,
        ],
    ],
];

// ── RECOVERY PROMPT MODIFIERS ─────────────────────────────────────────────────

const RECOVERY_PROMPT_MOD = "This person is on a recovery journey. Use compassionate, non-stigmatising language at all times. Never use the word 'relapse' in a shaming way — instead frame setbacks as 'difficult moments' or 'learning points'. Celebrate every day of clarity. Acknowledge how hard recovery work is. Help them identify triggers and build coping strategies with gentleness. If they share a setback, lead with compassion before any forward-looking question.";

/**
 * Get the label for an addiction category key.
 */
function addiction_category_label(string $key): string {
    return ADDICTION_CATEGORIES[$key]['label'] ?? ucfirst($key);
}

/**
 * Get the icon for an addiction category key.
 */
function addiction_category_icon(string $key): string {
    return ADDICTION_CATEGORIES[$key]['icon'] ?? '🛡️';
}

/**
 * Check if a user has a recovery focus set.
 */
function user_has_recovery_focus(array $coachProfile): bool {
    return !empty($coachProfile['addiction_focus']);
}
