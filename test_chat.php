<?php
require 'includes/db.php';
require 'includes/functions.php';
require 'providers/router.php';

$messages = [
    ['role' => 'user', 'content' => 'Hello, are you working?']
];

$systemPrompt = 'You are Solen, a helpful AI coach. Keep your response under 20 words.';

route_ai_request($messages, $systemPrompt, 500, [
    'provider'        => 'openrouter',
    'emotional_state' => 'neutral',
    'user_plan'       => 'premium',
    'model'           => null,
    'fallback'        => true,
    'user_id'         => 1,
    'current_text'    => 'Hello, are you working?'
]);
