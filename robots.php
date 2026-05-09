<?php
require_once __DIR__ . '/includes/db.php';
$base  = rtrim(get_setting('canonical_url') ?: SITE_URL, '/');
$index = get_setting('robots_index','1') === '1';
header('Content-Type: text/plain');
if (!$index) {
    echo "User-agent: *\nDisallow: /\n";
    exit;
}
?>
User-agent: *
Allow: /
Disallow: /admin/
Disallow: /api/
Disallow: /database/
Disallow: /app.php
Disallow: /dashboard.php
Disallow: /upgrade.php
Disallow: /upgrade-success.php
Disallow: /logout.php

Sitemap: <?= $base ?>/sitemap.xml
