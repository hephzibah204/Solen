<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/seo.php';

$slug = $_GET['slug'] ?? '';
$post = db_one("SELECT p.*, u.name as author_name FROM blog_posts p LEFT JOIN users u ON p.author_id=u.id WHERE p.slug=? AND p.status='published'", [$slug]);
if (!$post) { header('HTTP/1.0 404 Not Found'); include __DIR__.'/404.php'; exit; }

// Increment views
db_run("UPDATE blog_posts SET views=views+1 WHERE id=?", [$post['id']]);

// Related posts — same category first, fill with recent if needed
$related = db_query(
    "SELECT id, slug, title, category, published_at FROM blog_posts
      WHERE status='published' AND category=? AND id!=?
      ORDER BY published_at DESC LIMIT 3",
    [$post['category'], $post['id']]
);
if (count($related) < 3) {
    $exclude = array_merge([$post['id']], array_column($related, 'id'));
    $ph = implode(',', array_fill(0, count($exclude), '?'));
    $fill = db_query(
        "SELECT id, slug, title, category, published_at FROM blog_posts
          WHERE status='published' AND id NOT IN ($ph)
          ORDER BY published_at DESC LIMIT " . (3 - count($related)),
        $exclude
    );
    $related = array_merge($related, $fill);
}

// Prev / next navigation (chronological within same category)
$prevPost = db_one(
    "SELECT slug, title FROM blog_posts
      WHERE status='published' AND category=? AND published_at < ? AND id!=?
      ORDER BY published_at DESC LIMIT 1",
    [$post['category'], $post['published_at'], $post['id']]
);
$nextPost = db_one(
    "SELECT slug, title FROM blog_posts
      WHERE status='published' AND category=? AND published_at > ? AND id!=?
      ORDER BY published_at ASC LIMIT 1",
    [$post['category'], $post['published_at'], $post['id']]
);

$site = get_setting('site_name','Solen');
$metaTitle = $post['meta_title'] ?: $post['title'];
$metaDesc  = $post['meta_desc'] ?: excerpt($post['content']??'', 160);
?>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title><?= h($metaTitle) ?> — <?= h($site) ?></title>
<meta name="description" content="<?= h($metaDesc) ?>"/>
<link rel="canonical" href="<?= SITE_URL ?>/blog/<?= h($slug) ?>"/>
<meta property="og:title" content="<?= h($metaTitle) ?>"/>
<meta property="og:description" content="<?= h($metaDesc) ?>"/>
<meta property="og:type" content="article"/>
<?php
$base = rtrim(get_setting('canonical_url') ?: SITE_URL, '/');
$canon = $base . '/blog/' . $post['slug'];
$ogImg = $post['featured_image'] ?: get_setting('og_image','');
?>
<meta property="og:url" content="<?= h($canon) ?>"/>
<meta property="og:image" content="<?= h($ogImg) ?>"/>
<meta property="og:site_name" content="<?= h($site) ?>"/>
<meta name="twitter:card" content="summary_large_image"/>
<meta name="twitter:title" content="<?= h($metaTitle) ?>"/>
<meta name="twitter:description" content="<?= h($metaDesc) ?>"/>
<meta name="twitter:image" content="<?= h($ogImg) ?>"/>
<link rel="canonical" href="<?= h($canon) ?>"/>
<?php if(get_setting('robots_index','1')==='1'): ?>
<meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large"/>
<?php else: ?>
<meta name="robots" content="noindex"/>
<?php endif ?>
<script type="application/ld+json"><?= json_encode([
  "@context"=>"https://schema.org",
  "@type"=>"Article",
  "datePublished"=>$post['published_at'] ?? $post['created_at'],
  "dateModified"=>$post['updated_at'] ?? $post['created_at'],
  "image"=>$post['featured_image'] ?? get_setting('og_image'),
  "author"=>["@type"=>"Person","name"=>"Solen Editorial"],
  "publisher"=>["@type"=>"Organization","name"=>get_setting('site_name','Solen'),"logo"=>["@type"=>"ImageObject","url"=>SITE_URL."/assets/logo.png"]],
  "mainEntityOfPage"=>["@type"=>"WebPage","@id"=>$base.'/blog/'.$post['slug']],
  "@type"=>"Article",
  "headline"=>$post['title'],
  "description"=>$metaDesc,
  "author"=>["@type"=>"Person","name"=>$post['author_name']??$site],
  "datePublished"=>$post['published_at']??$post['created_at'],
  "publisher"=>["@type"=>"Organization","name"=>$site],
]) ?></script>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;1,300;1,400&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
<style>
*{box-sizing:border-box;margin:0;padding:0} body{background:#080810;color:#f0ede8;font-family:'DM Sans',sans-serif;line-height:1.7}
nav{padding:18px 0;border-bottom:1px solid rgba(255,255,255,0.07);background:rgba(8,8,16,0.9);position:sticky;top:0;z-index:10}
.nav-in{max-width:1100px;margin:0 auto;padding:0 24px;display:flex;align-items:center;justify-content:space-between}
.logo{font-family:'Cormorant Garamond',serif;font-size:24px;color:#c5a572;text-decoration:none}.logo span{color:#f0ede8}
.btn-cta{background:#c5a572;color:#1a1008;padding:9px 20px;border-radius:50px;font-size:13px;font-weight:500}
.wrap{max-width:740px;margin:0 auto;padding:0 24px}
.breadcrumb{padding:24px 0 0;font-size:13px;color:rgba(240,237,232,0.4)}
.breadcrumb a{color:#c5a572}
.article-header{padding:40px 0 32px}
.article-cat{font-size:12px;letter-spacing:0.1em;text-transform:uppercase;color:#c5a572;margin-bottom:14px}
h1{font-family:'Cormorant Garamond',serif;font-size:clamp(32px,4.5vw,52px);font-weight:400;line-height:1.12;margin-bottom:20px}
.article-meta{font-size:13px;color:rgba(240,237,232,0.4);display:flex;gap:20px;flex-wrap:wrap}
.article-meta span::before{content:'·';margin-right:20px}
.article-meta span:first-child::before{content:''}
.hero-img{width:100%;height:320px;background:linear-gradient(135deg,#1a0a2e,#2d1454);border-radius:16px;margin:32px 0;display:flex;align-items:center;justify-content:center;font-size:72px;overflow:hidden}
.hero-img img{width:100%;height:100%;object-fit:cover}
.article-body{font-size:17px;line-height:1.85;color:rgba(240,237,232,0.88)}
.article-body h2{font-family:'Cormorant Garamond',serif;font-size:28px;font-weight:400;margin:36px 0 14px;color:#f0ede8}
.article-body h3{font-family:'Cormorant Garamond',serif;font-size:22px;font-weight:400;margin:28px 0 10px;color:#f0ede8}
.article-body p{margin-bottom:20px}
.article-body ul,.article-body ol{margin:0 0 20px 24px}
.article-body li{margin-bottom:8px}
.article-body blockquote{border-left:2px solid #c5a572;padding:14px 20px;margin:28px 0;font-family:'Cormorant Garamond',serif;font-style:italic;font-size:21px;color:rgba(240,237,232,0.7);background:rgba(197,165,114,0.06);border-radius:0 8px 8px 0}
.article-body strong{color:#f0ede8}
.article-body a{color:#c5a572}
.cta-box{background:rgba(197,165,114,0.08);border:1px solid rgba(197,165,114,0.2);border-radius:16px;padding:28px 32px;margin:44px 0;text-align:center}
.cta-box h3{font-family:'Cormorant Garamond',serif;font-size:26px;font-weight:400;margin-bottom:10px}
.cta-box p{font-size:15px;color:rgba(240,237,232,0.6);margin-bottom:20px}
.cta-box a{display:inline-block;background:#c5a572;color:#1a1008;padding:12px 28px;border-radius:50px;font-size:14px;font-weight:500}
.divider{height:1px;background:rgba(255,255,255,0.07);margin:48px 0}
.related h2{font-family:'Cormorant Garamond',serif;font-size:28px;font-weight:400;margin-bottom:24px}
.related-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:60px}
.rel-card{background:#0e0e1a;border:1px solid rgba(255,255,255,0.07);border-radius:12px;padding:16px;transition:border-color 0.2s}
.rel-card:hover{border-color:rgba(197,165,114,0.3)}
.rel-card .cat{font-size:10px;letter-spacing:0.1em;text-transform:uppercase;color:#c5a572;margin-bottom:6px}
.rel-card .title{font-family:'Cormorant Garamond',serif;font-size:16px;line-height:1.3}
.rel-card a{color:#f0ede8}
footer{border-top:1px solid rgba(255,255,255,0.07);padding:32px 0;font-size:13px;color:rgba(240,237,232,0.35);text-align:center}
@media(max-width:600px){.related-grid{grid-template-columns:1fr}.article-meta{flex-direction:column;gap:4px}}
</style>
</head>
<body>
<nav>
  <div class="nav-in">
    <a href="/" class="logo">Sol<span>en</span></a>
    <a href="<?= is_logged_in() ? '/app.php' : '/register.php' ?>" class="btn-cta"><?= is_logged_in() ? 'Open App' : 'Start Free' ?></a>
  </div>
</nav>

<div class="wrap">
  <div class="breadcrumb">
    <a href="/blog.php">Blog</a> /
    <a href="/blog.php?category=<?= urlencode($post['category']) ?>"><?= h($post['category']) ?></a>
  </div>

  <header class="article-header">
    <div class="article-cat"><?= h($post['category']) ?></div>
    <h1><?= h($post['title']) ?></h1>
    <div class="article-meta">
      <span><?= h($post['author_name'] ?? 'Solen Team') ?></span>
      <span><?= format_date($post['published_at'] ?? $post['created_at']) ?></span>
      <span><?= $post['views'] ?> views</span>
    </div>
  </header>

  <?php if ($post['featured_image']): ?>
    <div class="hero-img"><img src="<?= h($post['featured_image']) ?>" alt="<?= h($post['title']) ?>"/></div>
  <?php endif ?>

  <article class="article-body">
    <?= $post['content'] ?? '<p>Content coming soon.</p>' ?>
  </article>

  <div class="cta-box">
    <h3>Start your wellness journey today</h3>
    <p>Get a personalized AI coach that remembers your story, tracks your mood, and grows with you.</p>
    <a href="<?= is_logged_in() ? '/app.php' : '/register.php' ?>"><?= is_logged_in() ? 'Open App →' : 'Try Solen free →' ?></a>
  </div>
</div>

<?php if ($related): ?>
<div class="wrap">
  <div class="divider"></div>
  <div class="related">
    <h2>More from <?= h($post['category']) ?></h2>
    <div class="related-grid">
      <?php foreach ($related as $r): ?>
      <div class="rel-card">
        <div class="cat"><?= h($r['category']) ?></div>
        <div class="title"><a href="/blog/<?= h($r['slug']) ?>"><?= h($r['title']) ?></a></div>
      </div>
      <?php endforeach ?>
    </div>
  </div>
</div>
<?php endif ?>

<?php if ($prevPost || $nextPost): ?>
<div class="wrap" style="padding-bottom:48px">
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;border-top:1px solid rgba(255,255,255,0.07);padding-top:32px">
    <?php if ($prevPost): ?>
    <a href="/blog/<?= h($prevPost['slug']) ?>" style="display:block;padding:18px 20px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);border-radius:14px;text-decoration:none;transition:border-color 0.2s" onmouseover="this.style.borderColor='rgba(197,165,114,0.3)'" onmouseout="this.style.borderColor='rgba(255,255,255,0.07)'">
      <div style="font-size:11px;color:rgba(240,237,232,0.35);margin-bottom:6px">← Previous in <?= h($post['category']) ?></div>
      <div style="font-size:14px;color:#f0ede8;line-height:1.4"><?= h($prevPost['title']) ?></div>
    </a>
    <?php else: ?><div></div><?php endif ?>
    <?php if ($nextPost): ?>
    <a href="/blog/<?= h($nextPost['slug']) ?>" style="display:block;padding:18px 20px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);border-radius:14px;text-decoration:none;text-align:right;transition:border-color 0.2s" onmouseover="this.style.borderColor='rgba(197,165,114,0.3)'" onmouseout="this.style.borderColor='rgba(255,255,255,0.07)'">
      <div style="font-size:11px;color:rgba(240,237,232,0.35);margin-bottom:6px">Next in <?= h($post['category']) ?> →</div>
      <div style="font-size:14px;color:#f0ede8;line-height:1.4"><?= h($nextPost['title']) ?></div>
    </a>
    <?php endif ?>
  </div>
</div>
<?php endif ?>

<footer>
  <div class="wrap">© 2026 <?= h($site) ?> Inc. &nbsp;·&nbsp; <a href="/blog.php" style="color:#c5a572">Blog</a> &nbsp;·&nbsp; <a href="/" style="color:#c5a572">Home</a></div>
</footer>
</body>
</html>
