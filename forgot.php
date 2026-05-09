<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) redirect('/app.php');

$state = 'form'; // form | sent | error
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $user = db_one("SELECT * FROM users WHERE email=?", [$email]);

            if ($user) {
                // Invalidate any previous unused tokens for this user
                db_run(
                    "UPDATE password_resets SET used_at=datetime('now') WHERE user_id=? AND used_at IS NULL",
                    [$user['id']]
                );

                // Generate a cryptographically secure token
                $token     = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $expires   = date('Y-m-d H:i:s', strtotime('+1 hour'));

                db_run(
                    "INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)",
                    [$user['id'], $tokenHash, $expires]
                );

                send_password_reset_email($user['email'], $user['name'], $token);
            }

            // Always show "sent" to prevent email enumeration
            $state = 'sent';
        }
    }
}

$site = get_setting('site_name', 'Solen');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Forgot Password — <?= h($site) ?></title>
<meta name="robots" content="noindex"/>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#07070f;--surface:#0d0d1e;
  --border:rgba(255,255,255,0.08);
  --accent:#b8956a;--accent-glow:rgba(184,149,106,0.18);
  --text:#f2ede8;--muted:rgba(242,237,232,0.45);
}
body{background:var(--bg);color:var(--text);font-family:'Outfit',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;position:relative;overflow:hidden}

/* ambient background orbs */
.orb{position:fixed;border-radius:50%;filter:blur(100px);pointer-events:none;animation:drift 14s ease-in-out infinite}
.orb-1{width:500px;height:500px;background:radial-gradient(circle,rgba(184,149,106,0.07),transparent);top:-100px;left:-100px}
.orb-2{width:400px;height:400px;background:radial-gradient(circle,rgba(99,102,241,0.07),transparent);bottom:-80px;right:-80px;animation-delay:-6s}
@keyframes drift{0%,100%{transform:translate(0,0)}50%{transform:translate(20px,-20px)}}

.card{
  position:relative;z-index:1;
  background:var(--surface);border:1px solid var(--border);border-radius:24px;
  padding:48px 44px;width:100%;max-width:420px;
  box-shadow:0 40px 80px rgba(0,0,0,0.35);
}

.logo{
  display:block;text-align:center;margin-bottom:36px;
  font-family:'Playfair Display',serif;font-size:26px;color:var(--accent);
  text-decoration:none;letter-spacing:0.02em;
}

.card-icon{
  width:60px;height:60px;border-radius:50%;
  background:rgba(184,149,106,0.1);border:1px solid rgba(184,149,106,0.2);
  display:flex;align-items:center;justify-content:center;
  font-size:26px;margin:0 auto 24px;
}

.card h1{
  font-family:'Playfair Display',serif;font-size:26px;font-weight:400;
  text-align:center;margin-bottom:10px;
}
.card p{
  font-size:14px;color:var(--muted);text-align:center;line-height:1.65;margin-bottom:32px;
}

.field{margin-bottom:22px}
.field label{
  display:block;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;
  color:var(--muted);margin-bottom:9px;font-weight:500;
}
.field input{
  width:100%;background:rgba(255,255,255,0.04);
  border:1px solid var(--border);border-radius:12px;
  padding:14px 18px;color:var(--text);
  font-family:'Outfit',sans-serif;font-size:15px;
  transition:all 0.25s;
}
.field input:focus{
  outline:none;border-color:var(--accent);
  background:rgba(184,149,106,0.03);
  box-shadow:0 0 0 3px var(--accent-glow);
}
.field input::placeholder{color:rgba(242,237,232,0.2)}

.btn-submit{
  width:100%;padding:15px;
  background:var(--accent);color:#1a1008;
  border:none;border-radius:50px;
  font-family:'Outfit',sans-serif;font-size:15px;font-weight:600;
  cursor:pointer;transition:all 0.25s;
}
.btn-submit:hover{background:#d4ae82;transform:translateY(-1px);box-shadow:0 8px 24px var(--accent-glow)}
.btn-submit:disabled{opacity:0.6;cursor:wait;transform:none}

.error-msg{
  background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);
  color:#fca5a5;padding:12px 16px;border-radius:10px;
  font-size:13px;margin-bottom:22px;
}

.back-link{
  display:block;text-align:center;margin-top:24px;
  font-size:13px;color:var(--muted);
}
.back-link a{color:var(--accent);text-decoration:none;font-weight:500}
.back-link a:hover{text-decoration:underline}

/* ── sent state ─────────────────────────────────────────────────── */
.sent-icon{
  width:72px;height:72px;border-radius:50%;
  background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.2);
  display:flex;align-items:center;justify-content:center;
  font-size:32px;margin:0 auto 24px;
}
.sent-card h1{color:#f0ede8}
.sent-card p{margin-bottom:0}
.sent-tip{
  margin-top:24px;padding:16px 18px;
  background:rgba(255,255,255,0.03);border:1px solid var(--border);
  border-radius:12px;font-size:13px;color:var(--muted);line-height:1.65;
  text-align:left;
}
.sent-tip strong{color:rgba(240,237,232,0.7)}
</style>
</head>
<body>
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>

<?php if ($state === 'sent'): ?>

<div class="card sent-card">
  <a href="/" class="logo"><?= h($site) ?></a>
  <div class="sent-icon">📬</div>
  <h1>Check your inbox</h1>
  <p>If an account exists for that email, we've sent a password reset link. It expires in 1 hour.</p>
  <div class="sent-tip">
    <strong>Don't see it?</strong> Check your spam or junk folder.
    The email comes from <strong><?= h(get_setting('from_email', 'hello@getsolen.com')) ?></strong>.
  </div>
  <div class="back-link"><a href="/login.php">← Back to sign in</a></div>
</div>

<?php else: ?>

<div class="card">
  <a href="/" class="logo"><?= h($site) ?></a>
  <div class="card-icon">🔑</div>
  <h1>Forgot your password?</h1>
  <p>Enter your account email and we'll send you a reset link.</p>

  <?php if ($error): ?>
    <div class="error-msg">⚠ <?= h($error) ?></div>
  <?php endif ?>

  <form method="POST" id="forgotForm">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>"/>
    <div class="field">
      <label for="email">Email address</label>
      <input
        type="email" id="email" name="email"
        required autofocus autocomplete="email"
        placeholder="you@example.com"
        value="<?= h($_POST['email'] ?? '') ?>"
      />
    </div>
    <button class="btn-submit" type="submit" id="submitBtn">Send Reset Link →</button>
  </form>

  <div class="back-link">
    Remember it? <a href="/login.php">Sign in</a>
    &nbsp;·&nbsp;
    New here? <a href="/register.php">Start free trial</a>
  </div>
</div>

<script>
document.getElementById('forgotForm').addEventListener('submit', function() {
  const btn = document.getElementById('submitBtn');
  btn.disabled = true;
  btn.textContent = 'Sending…';
});
</script>

<?php endif ?>
</body>
</html>
