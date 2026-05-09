<?php
require_once __DIR__ . '/includes/db.php';
$cols = db_query("PRAGMA table_info(blog_posts)");
foreach ($cols as $c) {
    echo $c['name'] . " (" . $c['type'] . ")\n";
}
