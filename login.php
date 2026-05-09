<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) redirect(is_admin() ? '/admin/index.php' : '/app.php');

// ── Brute-force protection ─────────────────────────────────────────────────
// Allow max 5 failed attempts, then lock for 15 minutes.
// Counter lives in the PHP session so it's per-browser (fast, no extra table).
$MAX_FAILS    = 5;
$LOCKOUT_SECS = 15 * 60; // 15 minutes

$fails    = $_SESSION['login_fails']   ?? 0;
$failTime = $_SESSION['login_fail_ts'] ?? 0;
$locked   = $fails >= $MAX_FAILS && (time() - $failTime) < $LOCKOUT_SECS;

// Auto-reset the counter after the lockout window passes
if ($fails >= $MAX_FAILS && (time() - $failTime) >= $LOCKOUT_SECS) {
    unset($_SESSION['login_fails'], $_SESSION['login_fail_ts']);
    $fails = 0; $locked = false;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($locked) {
        $error = 'Too many failed attempts. Please wait ' . ceil(($LOCKOUT_SECS - (time()-$failTime))/60) . ' minutes before trying again.';
    } else {
        $ok = login_user($_POST['email'] ?? '', $_POST['password'] ?? '');
        if ($ok) redirect(is_admin() ? '/admin/index.php' : ($_GET['next'] ?? '/app.php'));
        else {
            $fails = $_SESSION['login_fails'] ?? 0;
            $remaining = max(0, $MAX_FAILS - $fails);
            $error = $remaining > 0
                ? "Invalid email or password. {$remaining} attempt" . ($remaining===1?'':'s') . " remaining."
                : "Too many failed attempts. Please wait 15 minutes.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Sign In — Solen</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#07070f;--surface:#0d0d1e;--border:rgba(255,255,255,0.08);--accent:#b8956a;--accent-glow:rgba(184,149,106,0.2);--text:#f2ede8;--muted:rgba(242,237,232,0.45)}
body{background:var(--bg);color:var(--text);font-family:'Outfit',sans-serif;min-height:100vh;display:grid;grid-template-columns:1fr 1fr;overflow:hidden}
.left{position:relative;display:flex;flex-direction:column;justify-content:space-between;padding:40px 48px;overflow:hidden}
.left::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse 80% 60% at 30% 50%,rgba(184,149,106,0.08) 0%,transparent 70%)}
.orb{position:absolute;border-radius:50%;filter:blur(80px);opacity:0.6;animation:drift 12s ease-in-out infinite}
.orb-1{width:300px;height:300px;background:radial-gradient(circle,rgba(184,149,106,0.18),transparent);top:-60px;left:-80px}
.orb-2{width:400px;height:400px;background:radial-gradient(circle,rgba(120,80,200,0.12),transparent);bottom:-100px;right:-100px;animation-delay:-4s}
.orb-3{width:200px;height:200px;background:radial-gradient(circle,rgba(184,149,106,0.1),transparent);top:50%;left:60%;animation-delay:-7s}
@keyframes drift{0%,100%{transform:translate(0,0) scale(1)}33%{transform:translate(15px,-20px) scale(1.05)}66%{transform:translate(-10px,15px) scale(0.97)}}
.left-content{position:relative;z-index:1;display:flex;flex-direction:column;height:100%}
.logo-mark{font-family:'Playfair Display',serif;font-size:28px;color:var(--accent);text-decoration:none;display:flex;align-items:center;gap:12px}
.logo-dot{width:8px;height:8px;background:var(--accent);border-radius:50%;box-shadow:0 0 12px var(--accent)}
.hero-text{flex:1;display:flex;flex-direction:column;justify-content:center;padding:40px 0}
.hero-text h1{font-family:'Playfair Display',serif;font-size:clamp(32px,3.5vw,52px);font-weight:400;line-height:1.2;margin-bottom:20px}
.hero-text h1 em{font-style:italic;color:var(--accent)}
.hero-text p{color:var(--muted);font-size:16px;line-height:1.7;max-width:380px}
.testimonial{background:rgba(255,255,255,0.04);border:1px solid var(--border);border-radius:16px;padding:22px 24px}
.testimonial-text{font-family:'Playfair Display',serif;font-style:italic;font-size:16px;color:rgba(242,237,232,0.8);line-height:1.65;margin-bottom:16px}
.testimonial-author{display:flex;align-items:center;gap:10px}
.avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--accent),rgba(120,80,200,0.6));display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:600;color:#1a1008}
.author-info .name{font-size:13px;font-weight:500}
.author-info .role{font-size:11px;color:var(--muted)}
.right{background:var(--surface);display:flex;align-items:center;justify-content:center;padding:48px;position:relative}
.right::before{content:'';position:absolute;left:0;top:10%;bottom:10%;width:1px;background:linear-gradient(to bottom,transparent,var(--border) 30%,var(--border) 70%,transparent)}
.form-box{width:100%;max-width:400px}
.form-header{margin-bottom:40px}
.form-header h2{font-family:'Playfair Display',serif;font-size:32px;font-weight:400;margin-bottom:8px}
.form-header p{color:var(--muted);font-size:14px}
.field{margin-bottom:22px}
.field label{display:block;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;color:var(--muted);margin-bottom:9px;font-weight:500}
.field input{width:100%;background:rgba(255,255,255,0.04);border:1px solid var(--border);border-radius:12px;padding:14px 18px;color:var(--text);font-family:'Outfit',sans-serif;font-size:15px;transition:all 0.25s}
.field input:focus{outline:none;border-color:var(--accent);background:rgba(184,149,106,0.04);box-shadow:0 0 0 3px var(--accent-glow)}
.field input::placeholder{color:rgba(242,237,232,0.2)}
.btn-submit{width:100%;padding:15px;background:var(--accent);color:#1a1008;border:none;border-radius:50px;font-family:'Outfit',sans-serif;font-size:15px;font-weight:600;cursor:pointer;transition:all 0.25s;margin-top:6px}
.btn-submit:hover{background:#d4ae82;transform:translateY(-1px);box-shadow:0 8px 24px var(--accent-glow)}
.error-msg{background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);color:#fca5a5;padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:22px}
.divider{display:flex;align-items:center;gap:14px;margin:28px 0;color:var(--muted);font-size:12px}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--border)}
.signup-link{text-align:center;font-size:14px;color:var(--muted)}
.signup-link a{color:var(--accent);text-decoration:none;font-weight:500}
.forgot{font-size:12px;color:var(--muted);text-align:right;display:block;margin-top:8px;cursor:pointer}
.mobile-logo{display:none;font-family:'Playfair Display',serif;font-size:28px;color:var(--accent);text-decoration:none;align-items:center;gap:12px;margin-bottom:32px;justify-content:center}
@media(max-width:860px){body{grid-template-columns:1fr;overflow:auto}.left{display:none}.right{min-height:100vh;padding:32px 24px}.mobile-logo{display:flex}}
</style>
</head>
<body>
<div class="left">
  <div style="position:absolute;inset:0">
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>
  </div>
  <div class="left-content">
    <a href="/" class="logo-mark"><div class="logo-dot"></div>Solen</a>
    <div class="hero-text">
      <h1>Your personal wellness coach,<br><em>always listening.</em></h1>
      <p>A private space to process emotions, build resilience, and grow — guided by an AI that remembers you and meets you where you are.</p>
    </div>
    <div class="testimonial">
      <div class="testimonial-text">"I was skeptical, but Solen actually remembers our conversations. It feels like talking to someone who genuinely cares — not a chatbot."</div>
      <div class="testimonial-author">
        <div class="avatar">S</div>
        <div class="author-info">
          <div class="name">Sarah M.</div>
          <div class="role">Solen Pro · 4 months</div>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="right">
  <div class="form-box">
    <a href="/" class="mobile-logo"><div class="logo-dot"></div>Solen</a>
    <div class="form-header">
      <h2>Welcome back</h2>
      <p>Sign in to continue your journey with your coach.</p>
    </div>
    <?php if ($error): ?><div class="error-msg">⚠ <?= h($error) ?></div><?php endif ?>
    <form method="POST" action="/login.php" autocomplete="on">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>"/>
      <div class="field">
        <label>Email address</label>
        <input type="email" name="email" required autofocus autocomplete="email" value="<?= h($_POST['email'] ?? '') ?>" placeholder="you@example.com"/>
      </div>
      <div class="field">
        <label>Password</label>
        <input type="password" name="password" required autocomplete="current-password" placeholder="••••••••"/>
        <span class="forgot" onclick="location.href='/forgot.php'">Forgot password?</span>
      </div>
      <button class="btn-submit" type="submit">Sign In →</button>
    </form>
    <div class="divider">or</div>
    <div class="signup-link">New to Solen? <a href="/register.php">Start your free trial</a> — no card needed.</div>
  </div>
</div>
<script>
// Forcibly unregister the broken service worker that swallows POST requests
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.getRegistrations().then(function(registrations) {
    for(let registration of registrations) {
      registration.unregister();
    }
  });
}
// Ensure the button works
document.querySelector('form').addEventListener('submit', function(e) {
  const btn = document.querySelector('.btn-submit');
  btn.textContent = 'Signing in...';
  btn.style.opacity = '0.7';
});
</script>
</body>
</html>
