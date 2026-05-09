<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/seo.php';

$page    = max(1, (int)($_GET['page'] ?? 1));
$cat     = $_GET['cat'] ?? '';
$search  = trim($_GET['q'] ?? '');
$perPage = 9;

$where  = "status='published'";
$params = [];
if ($cat)    { $where .= " AND category=?";   $params[] = $cat; }
if ($search) { $where .= " AND (title LIKE ? OR content LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

$total = db_count('blog_posts', $where, $params);
$pg    = paginate($total, $page, $perPage);
$posts = db_query("SELECT p.*, u.name as author_name FROM blog_posts p LEFT JOIN users u ON p.author_id=u.id WHERE $where ORDER BY published_at DESC LIMIT ? OFFSET ?",
    array_merge($params, [$perPage, $pg['offset']]));
$cats  = db_query("SELECT DISTINCT category FROM blog_posts WHERE status='published' ORDER BY category");
$site  = get_setting('site_name','Solen');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Blog — <?= h($site) ?></title>
<meta name="description" content="Wellness insights, mental health tips, and personal growth guidance from the Solen team."/>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;1,400&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
<style>
*{box-sizing:border-box;margin:0;padding:0} body{background:#080810;color:#f0ede8;font-family:'DM Sans',sans-serif}
nav{padding:18px 0;border-bottom:1px solid rgba(255,255,255,0.07);background:rgba(8,8,16,0.9);position:sticky;top:0;z-index:10}
.nav-in{max-width:1100px;margin:0 auto;padding:0 24px;display:flex;align-items:center;justify-content:space-between}
.logo{font-family:'Cormorant Garamond',serif;font-size:24px;color:#c5a572;text-decoration:none}.logo span{color:#f0ede8}
.nav-links{display:flex;gap:24px;align-items:center}
.nav-links a{font-size:13px;color:rgba(240,237,232,0.5);letter-spacing:0.05em}
.nav-links a:hover{color:#f0ede8}
.btn-cta{background:#c5a572;color:#1a1008;padding:9px 20px;border-radius:50px;font-size:13px;font-weight:500}
.wrap{max-width:1100px;margin:0 auto;padding:0 24px}
.hero{padding:80px 0 56px;text-align:center}
.hero h1{font-family:'Cormorant Garamond',serif;font-size:clamp(36px,5vw,64px);font-weight:300;margin-bottom:14px}
.hero h1 em{font-style:italic;color:#c5a572}
.hero p{color:rgba(240,237,232,0.5);font-size:16px;max-width:500px;margin:0 auto}
.filters{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:40px}
.filter-link{padding:7px 18px;border-radius:50px;font-size:12px;border:1px solid rgba(255,255,255,0.1);color:rgba(240,237,232,0.5);transition:all 0.2s}
.filter-link:hover,.filter-link.active{border-color:#c5a572;color:#c5a572;background:rgba(197,165,114,0.08)}
.search-form{margin-left:auto;display:flex;gap:8px}
.search-input{background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:8px;padding:7px 14px;color:#f0ede8;font-family:'DM Sans',sans-serif;font-size:13px;width:200px}
.search-input:focus{outline:none;border-color:#c5a572}
.search-btn{background:#c5a572;color:#1a1008;border:none;border-radius:8px;padding:7px 14px;cursor:pointer;font-size:13px}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:24px;margin-bottom:40px}
.post-card{background:#0e0e1a;border:1px solid rgba(255,255,255,0.07);border-radius:16px;overflow:hidden;transition:all 0.2s;display:flex;flex-direction:column}
.post-card:hover{transform:translateY(-3px);border-color:rgba(197,165,114,0.3)}
.post-img{height:180px;background:linear-gradient(135deg,#1a0a2e,#2d1454);display:flex;align-items:center;justify-content:center;font-size:48px;flex-shrink:0}
.post-body{padding:20px;flex:1;display:flex;flex-direction:column}
.post-cat{font-size:11px;letter-spacing:0.1em;text-transform:uppercase;color:#c5a572;margin-bottom:8px}
.post-title{font-family:'Cormorant Garamond',serif;font-size:20px;font-weight:400;margin-bottom:10px;line-height:1.3}
.post-excerpt{font-size:13px;color:rgba(240,237,232,0.5);line-height:1.7;flex:1}
.post-meta{display:flex;align-items:center;justify-content:space-between;margin-top:16px;padding-top:14px;border-top:1px solid rgba(255,255,255,0.06);font-size:12px;color:rgba(240,237,232,0.35)}
.read-more{color:#c5a572;font-weight:500;text-decoration:none;font-size:13px}
.empty{text-align:center;padding:80px 0;color:rgba(240,237,232,0.35);font-size:15px}
.pagination{display:flex;gap:8px;justify-content:center;margin-bottom:60px}
.page-link{padding:8px 16px;border-radius:8px;border:1px solid rgba(255,255,255,0.1);color:rgba(240,237,232,0.5);font-size:13px}
.page-link:hover{border-color:#c5a572;color:#c5a572}
.page-link.cur{background:#c5a572;color:#1a1008;border-color:#c5a572}
footer{border-top:1px solid rgba(255,255,255,0.07);padding:32px 0;font-size:13px;color:rgba(240,237,232,0.35);text-align:center}
@media(max-width:768px){.grid{grid-template-columns:1fr}.nav-links{display:none}.filters{flex-wrap:wrap}.search-form{width:100%;margin-left:0}}
</style>
</head>
<body>
<nav>
  <div class="nav-in">
    <a href="/" class="logo">Sol<span>en</span></a>
    <div class="nav-links">
      <a href="/#features">Features</a>
      <a href="/pricing.php">Pricing</a>
      <a href="/blog.php" style="color:#c5a572">Blog</a>
      <?php if (is_logged_in()): ?>
        <a href="/app.php" class="btn-cta">Open App</a>
      <?php else: ?>
        <a href="/register.php" class="btn-cta">Start Free</a>
      <?php endif ?>
    </div>
  </div>
</nav>

<section class="hero">
  <div class="wrap">
    <h1>Wellness <em>Insights</em></h1>
    <p>Research-backed guides on mental wellness, anxiety, personal growth, and building a better relationship with yourself.</p>
  </div>
</section>

<div class="wrap">
  <div class="filters">
    <a href="/blog.php" class="filter-link <?= !$cat?'active':'' ?>">All</a>
    <?php foreach ($cats as $c): ?>
      <a href="/blog.php?cat=<?= urlencode($c['category']) ?>" class="filter-link <?= $cat===$c['category']?'active':'' ?>"><?= h($c['category']) ?></a>
    <?php endforeach ?>
    <form class="search-form" method="GET">
      <input class="search-input" name="q" placeholder="Search articles…" value="<?= h($search) ?>"/>
      <button class="search-btn" type="submit">Search</button>
    </form>
  </div>

  <?php if ($posts): ?>
  <div class="grid">
    <?php foreach ($posts as $p):
      $emojis = ['🧠','🌱','🌊','✨','🪞','🎯','💬','🌙','☀️'];
      $emoji = $emojis[crc32($p['slug']) % count($emojis)];
    ?>
    <article class="post-card">
      <?php if ($p['featured_image']): ?>
        <div class="post-img" style="background-image:url('<?= h($p['featured_image'])?>');background-size:cover;background-position:center"></div>
      <?php else: ?>
        <div class="post-img"><?= $emoji ?></div>
      <?php endif ?>
      <div class="post-body">
        <div class="post-cat"><?= h($p['category']) ?></div>
        <h2 class="post-title"><a href="/blog/<?= h($p['slug']) ?>" style="color:inherit"><?= h($p['title']) ?></a></h2>
        <p class="post-excerpt"><?= h($p['excerpt'] ?: excerpt($p['content'] ?? '', 120)) ?></p>
        <div class="post-meta">
          <span><?= h($p['author_name'] ?? 'Solen Team') ?> · <?= format_date($p['published_at'] ?? $p['created_at']) ?></span>
          <a class="read-more" href="/blog/<?= h($p['slug']) ?>">Read →</a>
        </div>
      </div>
    </article>
    <?php endforeach ?>
  </div>

  <?php if ($pg['pages'] > 1): ?>
  <div class="pagination">
    <?php for ($i=1; $i<=$pg['pages']; $i++): ?>
      <a class="page-link <?= $i===$page?'cur':'' ?>" href="?page=<?= $i ?><?= $cat?"&cat=".urlencode($cat):'' ?><?= $search?"&q=".urlencode($search):'' ?>"><?= $i ?></a>
    <?php endfor ?>
  </div>
  <?php endif ?>

  <?php else: ?>
  <div class="empty">
    <?php if ($search): ?>No articles found for "<?= h($search) ?>".
    <?php else: ?>No articles yet. Check back soon.<?php endif ?>
  </div>
  <?php endif ?>
</div>

<footer>
  <div class="wrap">© 2026 <?= h($site) ?> Inc. &nbsp;·&nbsp; <a href="/" style="color:#c5a572">Home</a> &nbsp;·&nbsp; <a href="/blog.php" style="color:#c5a572">Blog</a></div>
</footer>
</body>
</html>
