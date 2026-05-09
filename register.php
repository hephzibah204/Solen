<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
if (is_logged_in()) redirect('/app.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) { $error = 'Invalid request.'; }
    else {
        $result = register_user(trim($_POST['name'] ?? ''), trim($_POST['email'] ?? ''), trim($_POST['password'] ?? ''));
        if ($result['ok']) redirect('/app.php');
        else $error = $result['error'];
    }
}
$trialDays = get_setting('trial_days', '7');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Start Free Trial — Solen</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#07070f;--surface:#0d0d1e;--border:rgba(255,255,255,0.08);--accent:#b8956a;--accent-glow:rgba(184,149,106,0.2);--text:#f2ede8;--muted:rgba(242,237,232,0.45)}
body{background:var(--bg);color:var(--text);font-family:'Outfit',sans-serif;min-height:100vh;display:grid;grid-template-columns:1fr 1fr;overflow:hidden}
.left{position:relative;display:flex;flex-direction:column;padding:40px 48px;overflow:hidden;background:linear-gradient(150deg,rgba(184,149,106,0.05) 0%,transparent 60%)}
.orb{position:absolute;border-radius:50%;filter:blur(80px);opacity:0.5;animation:drift 12s ease-in-out infinite;pointer-events:none}
.orb-1{width:340px;height:340px;background:radial-gradient(circle,rgba(184,149,106,0.15),transparent);top:-80px;right:-40px}
.orb-2{width:280px;height:280px;background:radial-gradient(circle,rgba(99,102,241,0.12),transparent);bottom:-60px;left:-60px;animation-delay:-5s}
@keyframes drift{0%,100%{transform:translate(0,0)}50%{transform:translate(12px,-18px)}}
.left-content{position:relative;z-index:1;display:flex;flex-direction:column;height:100%;gap:40px}
.logo{font-family:'Playfair Display',serif;font-size:28px;color:var(--accent);text-decoration:none;display:flex;align-items:center;gap:12px}
.logo-dot{width:8px;height:8px;background:var(--accent);border-radius:50%;box-shadow:0 0 12px var(--accent)}
.left-hero h1{font-family:'Playfair Display',serif;font-size:clamp(30px,3vw,46px);font-weight:400;line-height:1.25;margin-bottom:18px}
.left-hero h1 em{font-style:italic;color:var(--accent)}
.left-hero p{color:var(--muted);font-size:15px;line-height:1.75;max-width:360px}
.perks{display:flex;flex-direction:column;gap:13px}
.perk{display:flex;align-items:flex-start;gap:13px;font-size:14px}
.perk-icon{width:28px;height:28px;border-radius:8px;background:var(--accent-glow);border:1px solid rgba(184,149,106,0.25);display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;margin-top:1px}
.perk-text strong{display:block;font-weight:500;margin-bottom:3px}
.perk-text span{color:var(--muted);font-size:13px;line-height:1.5}
.social-proof{background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:14px;padding:18px 20px;margin-top:auto}
.proof-row{display:flex;gap:16px;align-items:center}
.avatars{display:flex}
.mini-avatar{width:28px;height:28px;border-radius:50%;border:2px solid var(--bg);margin-left:-8px}
.mini-avatar:first-child{margin-left:0}
.proof-text{font-size:13px;color:var(--muted);line-height:1.5}
.proof-text strong{color:var(--text)}
.right{background:var(--surface);display:flex;align-items:center;justify-content:center;padding:48px;position:relative}
.right::before{content:'';position:absolute;left:0;top:10%;bottom:10%;width:1px;background:linear-gradient(to bottom,transparent,var(--border) 30%,var(--border) 70%,transparent)}
.form-box{width:100%;max-width:420px}
.form-header{margin-bottom:36px}
.form-header h2{font-family:'Playfair Display',serif;font-size:32px;font-weight:400;margin-bottom:8px}
.form-header p{color:var(--muted);font-size:14px;line-height:1.6}
.trial-badge{display:inline-flex;align-items:center;gap:7px;background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.2);border-radius:50px;padding:5px 14px;font-size:12px;color:#4ade80;margin-bottom:28px}
.field{margin-bottom:20px}
.field label{display:block;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;color:var(--muted);margin-bottom:9px;font-weight:500}
.field input{width:100%;background:rgba(255,255,255,0.04);border:1px solid var(--border);border-radius:12px;padding:14px 18px;color:var(--text);font-family:'Outfit',sans-serif;font-size:15px;transition:all 0.25s}
.field input:focus{outline:none;border-color:var(--accent);background:rgba(184,149,106,0.03);box-shadow:0 0 0 3px var(--accent-glow)}
.field input::placeholder{color:rgba(242,237,232,0.18)}
.btn-submit{width:100%;padding:15px;background:var(--accent);color:#1a1008;border:none;border-radius:50px;font-family:'Outfit',sans-serif;font-size:15px;font-weight:600;cursor:pointer;transition:all 0.25s;margin-top:8px;letter-spacing:0.02em}
.btn-submit:hover{background:#d4ae82;transform:translateY(-1px);box-shadow:0 8px 28px var(--accent-glow)}
.error-msg{background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);color:#fca5a5;padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:22px}
.login-link{text-align:center;font-size:14px;color:var(--muted);margin-top:24px}
.login-link a{color:var(--accent);font-weight:500;text-decoration:none}
.fine{text-align:center;font-size:11px;color:rgba(242,237,232,0.2);margin-top:16px;line-height:1.7}
@media(max-width:860px){body{grid-template-columns:1fr;overflow:auto}.left{display:none}.right{min-height:100vh;padding:32px 24px}}
</style>
</head>
<body>
<div class="left">
  <div class="orb orb-1"></div>
  <div class="orb orb-2"></div>
  <div class="left-content">
    <a href="/" class="logo"><div class="logo-dot"></div>Solen</a>
    <div class="left-hero">
      <h1>Your coach.<br>Your pace.<br><em>Your journey.</em></h1>
      <p>Solen gives you a private wellness coach powered by AI — one that actually remembers you, meets you where you are, and helps you grow, one conversation at a time.</p>
    </div>
    <div class="perks">
      <div class="perk">
        <div class="perk-icon">🧠</div>
        <div class="perk-text"><strong>Persistent memory</strong><span>Your coach remembers every session and builds on them</span></div>
      </div>
      <div class="perk">
        <div class="perk-icon">🎙️</div>
        <div class="perk-text"><strong>Voice sessions</strong><span>Talk out loud when typing isn't enough</span></div>
      </div>
      <div class="perk">
        <div class="perk-icon">📊</div>
        <div class="perk-text"><strong>Mood insights</strong><span>Track your emotional patterns over time</span></div>
      </div>
      <div class="perk">
        <div class="perk-icon">🌱</div>
        <div class="perk-text"><strong>Growth programs</strong><span>7-day guided journeys built for your goal</span></div>
      </div>
    </div>
    <div class="social-proof">
      <div class="proof-row">
        <div class="avatars">
          <div class="mini-avatar" style="background:linear-gradient(135deg,#b8956a,#7c3aed)"></div>
          <div class="mini-avatar" style="background:linear-gradient(135deg,#34d399,#059669)"></div>
          <div class="mini-avatar" style="background:linear-gradient(135deg,#60a5fa,#2563eb)"></div>
          <div class="mini-avatar" style="background:linear-gradient(135deg,#f59e0b,#d97706)"></div>
        </div>
        <div class="proof-text"><strong>2,400+ people</strong> on their wellness journey with Solen</div>
      </div>
    </div>
  </div>
</div>
<div class="right">
  <div class="form-box">
    <div class="form-header">
      <h2>Start for free</h2>
      <p>Create your account and meet your personalized wellness coach in minutes.</p>
    </div>
    <div class="trial-badge">✓ <?= $trialDays ?>-day free trial · No credit card required</div>
    <?php if ($error): ?><div class="error-msg">⚠ <?= h($error) ?></div><?php endif ?>
    <form method="POST" autocomplete="on">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>"/>
      <div class="field"><label>Your name</label><input type="text" name="name" required autofocus autocomplete="name" value="<?= h($_POST['name']??'') ?>" placeholder="Jane Smith"/></div>
      <div class="field"><label>Email address</label><input type="email" name="email" required autocomplete="email" value="<?= h($_POST['email']??'') ?>" placeholder="you@example.com"/></div>
      <div class="field"><label>Password <span style="opacity:0.5;font-size:10px">(min 8 characters)</span></label><input type="password" name="password" required minlength="8" autocomplete="new-password" placeholder="••••••••"/></div>
      <button class="btn-submit" type="submit">Create my account ✦</button>
    </form>
    <div class="login-link">Already have an account? <a href="/login.php">Sign in</a></div>
    <p class="fine">By creating an account you agree to our Terms and Privacy Policy.<br>Solen is not a medical service. In crisis? Call or text 988.</p>
  </div>
</div>
</body>
</html>
