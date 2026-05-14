<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
check_maintenance();
require_active_subscription();
$user = current_user();
$trialing = ($user['plan'] === 'free' && !empty($user['trial_ends']));
$trialDays = $trialing ? max(0, ceil((strtotime($user['trial_ends']) - time()) / 86400)) : 0;
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"/>
<?php pwa_head(); ?>
<title>Solen — Your Wellness Coach</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<style>
/* ── RESET & NATIVE BASE ───────────────────────────────────────────── */
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent;}
html{height:100%;overflow:hidden;}
body{
  height:100%;
  background:var(--bg);
  color:var(--text);
  font-family:'Outfit',sans-serif;
  overflow:hidden;
  overscroll-behavior:none;
  -webkit-overflow-scrolling:touch;
  display:flex;
  flex-direction:column;
  padding-top: env(safe-area-inset-top);
  padding-left: env(safe-area-inset-left);
  padding-right: env(safe-area-inset-right);
  padding-bottom: env(safe-area-inset-bottom);
}

/* ── DESIGN TOKENS ─────────────────────────────────────────────────── */
:root{
  --bg:#07070f;
  --surface:#0d0d1e;
  --border:rgba(255,255,255,0.07);
  --accent:#b8956a;
  --text:#f2ede8;
  --muted:rgba(242,237,232,0.42);
  --sat: env(safe-area-inset-top, 0px);
  --sab: env(safe-area-inset-bottom, 0px);
}

/* ── SITE NAV ───────────────────────────────────────────────────────── */
.solen-nav{
  background:rgba(7,7,15,0.98);
  border-bottom:1px solid rgba(255,255,255,0.07);
  backdrop-filter:blur(16px);
  flex-shrink:0;
  z-index:100;
}
.solen-nav-inner{
  max-width:480px;
  margin:0 auto;
  padding:0 16px;
  height:52px;
  display:flex;
  align-items:center;
  justify-content:space-between;
}
.solen-nav-logo{
  font-family:'Playfair Display',serif;
  font-size:19px;
  color:#b8956a;
  text-decoration:none;
  display:flex;
  align-items:center;
  gap:8px;
}
.solen-nav-dot{
  width:6px;height:6px;
  background:#b8956a;
  border-radius:50%;
  box-shadow:0 0 6px #b8956a;
  display:inline-block;
}
.solen-nav-links{
  display:flex;
  gap:2px;
}
.solen-nav-link{
  padding:6px 11px;
  border-radius:8px;
  font-size:12px;
  font-weight:500;
  color:rgba(242,237,232,0.42);
  text-decoration:none;
  transition:color 0.2s,background 0.2s;
  white-space:nowrap;
}
.solen-nav-link:hover{color:#f2ede8;background:rgba(255,255,255,0.06);}
.solen-nav-link.nav-active{color:#f2ede8;background:rgba(255,255,255,0.07);}

#root{ flex:1; min-height:0; display:flex; flex-direction:column; isolation:isolate; overflow:hidden; }

.trial-bar{
  background:rgba(184,149,106,0.1);
  border-bottom:1px solid rgba(184,149,106,0.18);
  padding:9px 20px;
  font-size:12px;
  display:flex;
  align-items:center;
  justify-content:space-between;
  color:var(--muted);
  font-family:'Outfit',sans-serif;
  flex-shrink:0;
  padding-top:max(9px, env(safe-area-inset-top));
}
.trial-bar a{color:var(--accent);text-decoration:none;font-weight:500;}

button,a,[role="button"]{ cursor:pointer; -webkit-tap-highlight-color:transparent; touch-action:manipulation; }
button{min-height:44px;}

textarea,input[type="text"]{
  -webkit-appearance:none;
  appearance:none;
  border-radius:16px;
  font-size:16px;
}

/* ── PWA INSTALL BANNER ────────────────────────────────────────────── */
#pwa-install-banner{
  position:fixed;
  bottom:0;
  left:0;
  right:0;
  z-index:9999;
  padding-bottom:max(20px, env(safe-area-inset-bottom));
  transform:translateY(110%);
  transition:transform 0.45s cubic-bezier(0.34,1.56,0.64,1);
  pointer-events:none;
}
#pwa-install-banner.visible{ transform:translateY(0); pointer-events:all; }
.pwa-banner-inner{
  margin:0 12px;
  background:linear-gradient(135deg,#0d0d1e 0%,#141428 100%);
  border:1px solid rgba(184,149,106,0.25);
  border-radius:24px;
  padding:20px 20px 24px;
  box-shadow:0 -10px 60px rgba(0,0,0,0.7);
  display:flex;
  flex-direction:column;
  gap:16px;
}
.pwa-banner-row1{ display:flex; align-items:center; gap:14px; }
.pwa-app-icon{ width:56px; height:56px; border-radius:14px; background:linear-gradient(135deg,#b8956a22,#b8956a44); border:1px solid rgba(184,149,106,0.3); display:flex; align-items:center; justify-content:center; overflow:hidden; }
.pwa-app-icon img{width:100%; height:100%; object-fit:cover;}
.pwa-info{flex:1; min-width:0;}
.pwa-title{ font-family:'Playfair Display',serif; font-size:17px; color:#f2ede8; font-weight:500; }
.pwa-subtitle{ font-size:12px; color:rgba(242,237,232,0.45); margin-top:3px; line-height:1.5; }
.pwa-close{ width:28px; height:28px; border-radius:50%; background:rgba(255,255,255,0.07); border:none; color:rgba(242,237,232,0.4); font-size:14px; display:flex; align-items:center; justify-content:center; }
.pwa-btn-row{ display:flex; gap:10px; }
.pwa-btn-install{ flex:1; background:var(--accent); color:#1a1008; border:none; border-radius:50px; font-family:'Outfit',sans-serif; font-size:15px; font-weight:600; padding:13px 20px; }
.pwa-btn-later{ background:rgba(255,255,255,0.06); color:rgba(242,237,232,0.5); border:1px solid rgba(255,255,255,0.08); border-radius:50px; font-family:'Outfit',sans-serif; font-size:14px; padding:13px 20px; }
#pwa-ios-guide{ display:none; font-size:12px; color:rgba(242,237,232,0.4); text-align:center; line-height:1.7; }

@keyframes pulse{ 0%,100%{box-shadow:0 0 0 0 rgba(184,149,106,0.4);} 50%{box-shadow:0 0 0 8px rgba(184,149,106,0);} }
.pwa-btn-install{animation:pulse 2.5s ease-in-out infinite;}
</style>
</head>
<body>
<nav class="solen-nav">
  <div class="solen-nav-inner">
    <a href="/dashboard.php" class="solen-nav-logo">
      <span class="solen-nav-dot"></span>Solen
    </a>
    <div class="solen-nav-links">
      <a href="/dashboard.php" class="solen-nav-link">Dashboard</a>
      <span class="solen-nav-link nav-active">Chat</span>
      <a href="/rituals.php" class="solen-nav-link">Rituals</a>
      <a href="/timeline.php" class="solen-nav-link">Growth</a>
      <a href="/logout.php" class="solen-nav-link">Logout</a>
    </div>
  </div>
</nav>
<?php if ($trialing && $trialDays <= 3): ?>
<div class="trial-bar">
  <span>⏳ <?= $trialDays ?> day<?= $trialDays!=1?'s':'' ?> left on your free trial</span>
  <a href="/pricing.php">Upgrade to keep access →</a>
</div>
<?php endif ?>
<div id="root"></div>
<script>
window.SOLEN_USER = { id: <?= json_encode($user['id']) ?>, name: <?= json_encode($user['name']) ?>, email: <?= json_encode($user['email']) ?> };
window.SOLEN_API_BASE = '/api';
window.SOLEN_AI_PROVIDER = <?= json_encode(get_setting('ai_provider','auto')) ?>;
</script>
<script src="/assets/react.production.min.js"></script>
<script src="/assets/react-dom.production.min.js"></script>
<script src="/assets/app.bundle.js?v=<?= filemtime(__DIR__ . '/assets/app.bundle.js') ?>"></script>
<?php pwa_body(); ?>
</body>
</html>
