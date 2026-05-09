<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (get_setting('sitemap_enabled', '1') !== '1') { http_response_code(404); exit; }

$base = rtrim(get_setting('canonical_url') ?: SITE_URL, '/');
$lang = h(get_setting('hreflang', 'en-US'));

header('Content-Type: application/xml; charset=UTF-8');

// Pull all published posts with the data we need for lastmod + changefreq
$posts      = db_query("SELECT slug, title, category, published_at, updated_at FROM blog_posts WHERE status='published' ORDER BY published_at DESC");
$categories = array_values(array_unique(array_filter(array_column($posts, 'category'))));

// Helper: pick the most-recently-touched date between two nullable strings
function freshest(?string $a, ?string $b): string {
    $ta = $a ? strtotime($a) : 0;
    $tb = $b ? strtotime($b) : 0;
    return date('Y-m-d', max($ta, $tb) ?: time());
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">

  <!-- ── Core marketing pages ── -->
  <url>
    <loc><?= $base ?>/</loc>
    <changefreq>weekly</changefreq>
    <priority>1.0</priority>
    <xhtml:link rel="alternate" hreflang="<?= $lang ?>" href="<?= $base ?>/"/>
  </url>
  <url><loc><?= $base ?>/pricing.php</loc><changefreq>weekly</changefreq><priority>0.9</priority></url>
  <url><loc><?= $base ?>/register.php</loc><changefreq>monthly</changefreq><priority>0.8</priority></url>
  <url><loc><?= $base ?>/blog.php</loc><changefreq>daily</changefreq><priority>0.8</priority>
<?php if ($posts): ?>
    <lastmod><?= freshest($posts[0]['published_at'], $posts[0]['updated_at']) ?></lastmod>
<?php endif ?>
  </url>
  <url><loc><?= $base ?>/login.php</loc><changefreq>monthly</changefreq><priority>0.4</priority></url>

<?php if ($categories): ?>
  <!-- ── Blog category pages ── -->
<?php foreach ($categories as $cat): ?>
  <url>
    <loc><?= $base ?>/blog.php?category=<?= urlencode($cat) ?></loc>
    <changefreq>weekly</changefreq>
    <priority>0.6</priority>
  </url>
<?php endforeach ?>

<?php endif ?>
<?php if ($posts): ?>
  <!-- ── Blog posts (<?= count($posts) ?> published) ── -->
<?php foreach ($posts as $p):
    $loc     = $base . '/blog/' . h($p['slug']);
    $lastmod = freshest($p['published_at'], $p['updated_at']);
    // Posts older than 90 days change rarely; recent ones change more
    $age     = (time() - strtotime($p['published_at'] ?: $p['updated_at'])) / 86400;
    $freq    = $age < 7 ? 'daily' : ($age < 90 ? 'weekly' : 'monthly');
?>
  <url>
    <loc><?= $loc ?></loc>
    <lastmod><?= $lastmod ?></lastmod>
    <changefreq><?= $freq ?></changefreq>
    <priority>0.7</priority>
  </url>
<?php endforeach ?>
<?php endif ?>

</urlset>
