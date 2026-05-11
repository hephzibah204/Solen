<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Maintenance mode check
check_maintenance();

// Serve the landing page HTML directly
$landingPath = __DIR__ . '/landing-page.html';
if (file_exists($landingPath)) {
    // Inject nav state for logged-in users
    $html = file_get_contents($landingPath);
    if (is_logged_in()) {
        $html = str_replace(
            '<a href="/register.php" class="btn-nav">Start Free Trial</a>',
            '<a href="/app.php" class="btn-nav">Open App</a>',
            $html
        );
        $html = str_replace(
            'href="/register.php"',
            'href="/app.php"',
            $html
        );
        $html = preg_replace(
            '/<a href="\/login\.php" class="nav-login"[^>]*>Log in<\/a>/i',
            '',
            $html
        );
    }
    echo $html;
} else {
    // Fallback if landing page not present
    redirect('/register.php');
}
