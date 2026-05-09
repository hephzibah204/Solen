<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();
$user    = current_user();
$coach   = db_one("SELECT * FROM coach_profiles WHERE user_id=?", [$user['id']]);
$sub     = db_one("SELECT * FROM subscriptions WHERE user_id=? ORDER BY id DESC LIMIT 1", [$user['id']]);
$moods   = db_query("SELECT * FROM mood_logs WHERE user_id=? ORDER BY logged_date DESC LIMIT 7", [$user['id']]);
$mem     = db_count('coach_memory', 'user_id=?', [$user['id']]);
$avgMood = count($moods) ? number_format(array_sum(array_column($moods,'score')) / count($moods), 1) : null;
$site    = get_setting('site_name','Solen');
$trialLeft = null;
if ($user['plan'] === 'free' && $user['trial_ends']) {
    $trialLeft = max(0, ceil((strtotime($user['trial_ends']) - time()) / 86400));
}
$firstName = explode(' ', $user['name'])[0];
$hour = (int)date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$moodColors = [1=>'#ef4444',2=>'#f97316',3=>'#eab308',4=>'#84cc16',5=>'#22c55e'];
$moodEmojis = [1=>'😔',2=>'😕',3=>'😐',4=>'🙂',5=>'😊'];
$days = [];
for ($i=6;$i>=0;$i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $m = null;
    foreach ($moods as $ml) { if ($ml['logged_date']===$d) { $m=$ml; break; } }
    $days[] = ['date'=>$d,'day'=>date('D',strtotime($d)),'mood'=>$m];
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"/>
<title>Dashboard — <?= h($site) ?></title>
<?php require_once __DIR__ . '/includes/pwa.php'; pwa_head(); ?>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#07070f;--surface:#0c0c1a;--surface2:#111128;
  --border:rgba(255,255,255,0.07);--border2:rgba(255,255,255,0.04);
  --accent:#b8956a;--accent2:rgba(184,149,106,0.12);
  --text:#f2ede8;--muted:rgba(242,237,232,0.42);
}
body{background:var(--bg);color:var(--text);font-family:'Outfit',sans-serif;min-height:100vh}
a{text-decoration:none}

/* Nav */
nav{padding:0;border-bottom:1px solid var(--border);background:rgba(7,7,15,0.95);position:sticky;top:0;z-index:50;backdrop-filter:blur(12px)}
.nav-in{max-width:1080px;margin:0 auto;padding:0 28px;display:flex;align-items:center;justify-content:space-between;height:58px}
.logo{font-family:'Playfair Display',serif;font-size:24px;color:var(--accent);display:flex;align-items:center;gap:10px}
.logo-dot{width:7px;height:7px;background:var(--accent);border-radius:50%;box-shadow:0 0 8px var(--accent)}
.nav-right{display:flex;gap:10px;align-items:center}
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 20px;border-radius:50px;font-family:'Outfit',sans-serif;font-size:13px;font-weight:500;cursor:pointer;border:none;transition:all 0.2s;text-decoration:none;letter-spacing:0.01em}
.btn-gold{background:var(--accent);color:#1a1008}
.btn-gold:hover{background:#d4ae82;box-shadow:0 6px 20px rgba(184,149,106,0.3)}
.btn-ghost{background:transparent;border:1px solid var(--border);color:var(--muted)}
.btn-ghost:hover{border-color:var(--accent);color:var(--accent)}

/* Layout */
.wrap{max-width:1080px;margin:0 auto;padding:36px 28px 60px}

/* Page header */
.page-header{margin-bottom:36px;display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap}
.page-header h1{font-family:'Playfair Display',serif;font-size:clamp(28px,4vw,42px);font-weight:400;letter-spacing:-0.01em;line-height:1.15}
.page-header h1 em{font-style:italic;color:var(--accent)}
.page-header p{color:var(--muted);font-size:15px;margin-top:7px}

/* Trial banner */
.trial-banner{background:var(--accent2);border:1px solid rgba(184,149,106,0.22);border-radius:16px;padding:18px 24px;display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;gap:12px;flex-wrap:wrap}
.trial-banner p{font-size:14px;color:var(--muted)}
.trial-banner strong{color:var(--accent)}

/* Stats grid */
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px}
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:18px;padding:22px 20px;position:relative;overflow:hidden;transition:border 0.2s}
.stat-card:hover{border-color:rgba(255,255,255,0.12)}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,rgba(255,255,255,0.06),transparent)}
.stat-label{font-size:10px;letter-spacing:0.12em;text-transform:uppercase;color:var(--muted);margin-bottom:10px;font-weight:500}
.stat-value{font-family:'Playfair Display',serif;font-size:40px;color:var(--accent);line-height:1;margin-bottom:5px}
.stat-sub{font-size:12px;color:var(--muted)}

/* Two col */
.two-col{display:grid;grid-template-columns:1.4fr 1fr;gap:16px;margin-bottom:24px}
.card{background:var(--surface);border:1px solid var(--border);border-radius:18px;padding:24px}
.card-title{font-size:11px;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;color:var(--muted);margin-bottom:18px}

/* Mood bar */
.mood-bar{display:flex;justify-content:space-between;gap:6px;margin-bottom:16px}
.mood-day{display:flex;flex-direction:column;align-items:center;gap:5px;flex:1}
.mood-strip{width:100%;height:6px;border-radius:3px;background:rgba(255,255,255,0.05)}
.mood-day-label{font-size:10px;color:var(--muted)}

/* Quick actions */
.actions-title{font-size:13px;font-weight:600;color:var(--muted);margin-bottom:14px;letter-spacing:0.04em;text-transform:uppercase;font-size:10px}
.action-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
.action-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:22px 18px;text-decoration:none;color:var(--text);transition:all 0.2s;display:flex;flex-direction:column;gap:9px;position:relative;overflow:hidden}
.action-card:hover{border-color:rgba(184,149,106,0.3);transform:translateY(-2px);box-shadow:0 8px 32px rgba(0,0,0,0.3)}
.action-card .ac-icon{font-size:22px;line-height:1}
.action-card .ac-title{font-size:14px;font-weight:500;line-height:1.3}
.action-card .ac-desc{font-size:12px;color:var(--muted);line-height:1.4}

/* Plan card */
.plan-name{font-family:'Playfair Display',serif;font-size:24px;color:var(--accent);margin-bottom:6px}
.plan-status{font-size:13px;color:var(--muted);margin-bottom:20px}

/* Coach profile */
.coach-pill{display:inline-flex;align-items:center;gap:10px;background:var(--accent2);border:1px solid rgba(184,149,106,0.2);border-radius:50px;padding:8px 16px 8px 8px}
.coach-avatar{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--accent),#7c3aed);display:flex;align-items:center;justify-content:center;font-size:13px;color:#fff;font-weight:600}
.coach-name{font-size:14px;font-weight:500}
.coach-type{font-size:11px;color:var(--muted)}

@media(max-width:900px){.stats-grid{grid-template-columns:1fr 1fr}.two-col{grid-template-columns:1fr}.action-grid{grid-template-columns:1fr 1fr}}
@media(max-width:600px){.stats-grid{grid-template-columns:1fr 1fr}.action-grid{grid-template-columns:1fr 1fr}}
</style>
</head>
<body>
<nav>
  <div class="nav-in">
    <a href="/" class="logo"><div class="logo-dot"></div>Solen</a>
    <div class="nav-right">
      <?php if ($coach): ?>
        <div class="coach-pill">
          <div class="coach-avatar"><?= mb_substr(h($coach['coach_name']),0,1) ?></div>
          <div><div class="coach-name"><?= h($coach['coach_name']) ?></div><div class="coach-type"><?= h($coach['purpose'] ?? 'wellness') ?> coach</div></div>
        </div>
      <?php endif ?>
      <a href="/app.php" class="btn btn-gold">Open Coach</a>
      <?php if ($user['role']==='admin'): ?><a href="/admin/index.php" class="btn btn-ghost">Admin</a><?php endif ?>
    </div>
  </div>
</nav>

<div class="wrap">

  <!-- Page header -->
  <div class="page-header">
    <div>
      <h1><?= $greeting ?>, <em><?= h($firstName) ?></em></h1>
      <p><?= date('l, F j') ?> · <?= $coach ? h($coach['coach_name']).' is ready for you' : 'Set up your coach to begin' ?></p>
    </div>
    <a href="/logout.php" style="color:var(--muted);font-size:13px;align-self:center;transition:color 0.2s" onmouseover="this.style.color='#f2ede8'" onmouseout="this.style.color=''">Sign out →</a>
  </div>

  <!-- Trial banner -->
  <?php if ($trialLeft !== null && $trialLeft <= 3): ?>
  <div class="trial-banner">
    <p>⏳ You have <strong><?= $trialLeft ?> day<?= $trialLeft!=1?'s':'' ?></strong> left on your free trial.</p>
    <a href="/pricing.php" class="btn btn-gold">Upgrade now →</a>
  </div>
  <?php endif ?>

  <!-- Stats -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-label">Day Streak</div>
      <div class="stat-value"><?= $coach['day_streak'] ?? 0 ?></div>
      <div class="stat-sub">days in a row 🔥</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Emotional Pulse</div>
      <div style="height:40px;margin-top:10px">
        <canvas id="heartbeatCanvas" width="120" height="40"></canvas>
      </div>
      <div class="stat-sub">real-time stability</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Avg Mood (7d)</div>
      <div class="stat-value"><?= $avgMood ?? '—' ?></div>
      <div class="stat-sub">out of 5.0</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Intelligence</div>
      <div class="stat-value" style="font-size:18px;margin-top:12px;font-family:'Outfit',sans-serif;font-weight:600">
        <?= h($coach['growth_stage'] ?? 'Exploration') ?>
      </div>
      <div class="stat-sub">current growth phase</div>
    </div>
  </div>

  <!-- Two column -->
  <div class="two-col">
    <!-- Mood chart -->
    <div class="card">
      <div class="card-title">This Week's Mood</div>
      <div class="mood-bar">
        <?php foreach ($days as $d): ?>
        <div class="mood-day">
          <div class="mood-strip" style="background:<?= $d['mood'] ? $moodColors[$d['mood']['score']] : 'rgba(255,255,255,0.06)' ?>"></div>
          <span style="font-size:15px"><?= $d['mood'] ? $moodEmojis[$d['mood']['score']] : '·' ?></span>
          <span class="mood-day-label"><?= $d['day'][0] ?></span>
        </div>
        <?php endforeach ?>
      </div>
      <?php if ($avgMood): ?>
      <div style="display:flex;align-items:center;gap:8px;padding:12px 14px;background:rgba(255,255,255,0.03);border-radius:10px;margin-bottom:16px">
        <span style="font-size:20px"><?= $moodEmojis[min(5,max(1,round($avgMood)))] ?></span>
        <span style="font-size:13px;color:var(--muted)">7-day average: <strong style="color:var(--text)"><?= $avgMood ?>/5</strong></span>
      </div>
      <?php endif ?>
      <a href="/app.php" class="btn btn-ghost" style="width:100%;justify-content:center;border-radius:10px">Log today's mood in coach →</a>
    </div>

    <!-- Life Intelligence Snapshot -->
    <div class="card" id="intel-card">
      <div class="card-title">Life Intelligence</div>
      <div id="intel-content">
        <div style="font-size:14px;color:var(--muted);line-height:1.6;margin-bottom:20px">
          Analyzing your emotional evolution and growth patterns...
        </div>
        <button onclick="fetchIntel()" class="btn btn-ghost" style="width:100%;justify-content:center;border-radius:10px">Refresh Analysis</button>
      </div>
    </div>
  </div>

  <script>
    // Heartbeat Animation
    const canvas = document.getElementById('heartbeatCanvas');
    const ctx = canvas.getContext('2d');
    let frame = 0;
    function animateHeartbeat() {
      frame++;
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      ctx.beginPath();
      ctx.lineWidth = 2;
      ctx.strokeStyle = '#b8956a';
      ctx.lineCap = 'round';
      const mid = canvas.height / 2;
      for (let x = 0; x < canvas.width; x++) {
        const y = mid + Math.sin(x * 0.08 + frame * 0.1) * 8;
        if (x === 0) ctx.moveTo(x, y);
        else ctx.lineTo(x, y);
      }
      ctx.stroke();
      requestAnimationFrame(animateHeartbeat);
    }
    animateHeartbeat();

    // Life Intelligence Fetch
    async function fetchIntel() {
      const container = document.getElementById('intel-content');
      container.innerHTML = '<div style="font-size:14px;color:var(--muted);opacity:0.5">Solen is reflecting...</div>';
      try {
        const r = await fetch('/api/data.php?action=get_life_intelligence', {method:'POST'});
        const d = await r.json();
        if (d.intelligence) {
          container.innerHTML = `
            <div style="margin-bottom:12px;font-family:'Playfair Display',serif;font-size:18px;color:var(--accent)">${d.intelligence.life_phase}</div>
            <div style="font-size:13px;color:var(--muted);line-height:1.6;margin-bottom:16px">${d.intelligence.insight}</div>
            <a href="/app.php" class="btn btn-gold" style="width:100%;justify-content:center;border-radius:10px">Deep Dive in Chat</a>
          `;
        } else {
          container.innerHTML = '<div style="font-size:13px;color:var(--muted)">Not enough sessions for deep analysis yet.</div>';
        }
      } catch(e) {
        container.innerHTML = '<div style="font-size:13px;color:var(--red)">Failed to load analysis.</div>';
      }
    }
    fetchIntel();
  </script>

  <!-- Quick actions -->
  <div class="actions-title">Quick Actions</div>
  <div class="action-grid">
    <a href="/app.php" class="action-card" style="border-color:rgba(184,149,106,0.18)">
      <div class="ac-icon">💬</div>
      <div class="ac-title">Chat with <?= h($coach['coach_name'] ?? 'Your Coach') ?></div>
      <div class="ac-desc">Continue your wellness journey</div>
    </a>
    <a href="/insights.php" class="action-card">
      <div class="ac-icon">📊</div>
      <div class="ac-title">Wellness Insights</div>
      <div class="ac-desc">View your growth snapshot</div>
    </a>
    <a href="/app.php" class="action-card" style="border-color:rgba(184,149,106,0.3)">
      <div class="ac-icon">✨</div>
      <div class="ac-title">Companion Evolution</div>
      <div class="ac-desc">
        <?php
        $levels = [1=>'Stranger', 2=>'Acquaintance', 3=>'Trusted Companion', 4=>'Core Support'];
        echo $levels[$coach['relationship_level'] ?? 1];
        ?> · <?= ucfirst($coach['personality_style'] ?? 'Gentle') ?> style
      </div>
    </a>
    <a href="/pricing.php" class="action-card">
      <div class="ac-icon">⭐</div>
      <div class="ac-title">Membership</div>
      <div class="ac-desc">Upgrade for advanced features</div>
    </a>
    <a href="/blog.php" class="action-card">
      <div class="ac-icon">✍️</div>
      <div class="ac-title">Read the Blog</div>
      <div class="ac-desc">Wellness insights and guides</div>
    </a>
    <a href="/privacy.php" class="action-card">
      <div class="ac-icon">🛡️</div>
      <div class="ac-title">Privacy & Memory</div>
      <div class="ac-desc">Manage what Solen remembers</div>
    </a>
  </div>

</div>
<?php pwa_body(); ?>
</body>
</html>
