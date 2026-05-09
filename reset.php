<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) redirect('/app.php');

$rawToken  = trim($_GET['token'] ?? '');
$tokenHash = $rawToken ? hash('sha256', $rawToken) : '';
$state     = 'form'; // form | success | invalid
$error     = '';

// ── Validate the token upfront ────────────────────────────────────────────
$resetRow = null;
if ($tokenHash) {
    $resetRow = db_one(
        "SELECT r.*, u.email, u.name FROM password_resets r
         JOIN users u ON u.id = r.user_id
         WHERE r.token_hash = ?
           AND r.used_at IS NULL
           AND r.expires_at > datetime('now')
         LIMIT 1",
        [$tokenHash]
    );
}

if (!$resetRow && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $state = 'invalid';
}

// ── Handle form submission ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $resetRow) {
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $password2) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // Update password
        db_run("UPDATE users SET password=? WHERE id=?", [$hash, $resetRow['user_id']]);

        // Mark token as used
        db_run(
            "UPDATE password_resets SET used_at=datetime('now') WHERE token_hash=?",
            [$tokenHash]
        );

        // Invalidate all other unused tokens for this user
        db_run(
            "UPDATE password_resets SET used_at=datetime('now') WHERE user_id=? AND used_at IS NULL",
            [$resetRow['user_id']]
        );

        // Log the user in automatically
        $_SESSION['user_id']   = $resetRow['user_id'];
        $_SESSION['user_role'] = 'user';
        $_SESSION['user_name'] = $resetRow['name'];
        db_run("UPDATE users SET last_login=datetime('now') WHERE id=?", [$resetRow['user_id']]);

        $state = 'success';
    }
}

$site = get_setting('site_name', 'Solen');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Reset Password — <?= h($site) ?></title>
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
  display:flex;align-items:center;justify-content:center;
  font-size:28px;margin:0 auto 24px;
}
.card-icon.lock{background:rgba(184,149,106,0.1);border:1px solid rgba(184,149,106,0.2)}
.card-icon.success{background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.2)}
.card-icon.invalid{background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2)}

.card h1{
  font-family:'Playfair Display',serif;font-size:26px;font-weight:400;
  text-align:center;margin-bottom:10px;
}
.card p{font-size:14px;color:var(--muted);text-align:center;line-height:1.65;margin-bottom:28px}

.field{margin-bottom:22px}
.field label{
  display:block;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;
  color:var(--muted);margin-bottom:9px;font-weight:500;
}
.field{position:relative}
.field input{
  width:100%;background:rgba(255,255,255,0.04);
  border:1px solid var(--border);border-radius:12px;
  padding:14px 48px 14px 18px;color:var(--text);
  font-family:'Outfit',sans-serif;font-size:15px;
  transition:all 0.25s;
}
.field input:focus{
  outline:none;border-color:var(--accent);
  background:rgba(184,149,106,0.03);
  box-shadow:0 0 0 3px var(--accent-glow);
}
.field input::placeholder{color:rgba(242,237,232,0.2)}
.toggle-pw{
  position:absolute;right:14px;bottom:14px;
  background:none;border:none;cursor:pointer;
  color:var(--muted);font-size:17px;padding:2px;
  transition:color 0.2s;
}
.toggle-pw:hover{color:var(--text)}

/* strength bar */
.strength-wrap{margin-top:8px}
.strength-bar{height:3px;border-radius:2px;background:rgba(255,255,255,0.08);overflow:hidden;transition:all 0.3s}
.strength-fill{height:100%;width:0;border-radius:2px;transition:width 0.35s,background 0.35s}
.strength-label{font-size:11px;color:var(--muted);margin-top:5px}

.btn-submit{
  width:100%;padding:15px;
  background:var(--accent);color:#1a1008;
  border:none;border-radius:50px;
  font-family:'Outfit',sans-serif;font-size:15px;font-weight:600;
  cursor:pointer;transition:all 0.25s;margin-top:6px;
}
.btn-submit:hover{background:#d4ae82;transform:translateY(-1px);box-shadow:0 8px 24px var(--accent-glow)}
.btn-submit:disabled{opacity:0.5;cursor:wait;transform:none}

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
</style>
</head>
<body>
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>

<?php if ($state === 'invalid'): ?>

  <div class="card">
    <a href="/" class="logo"><?= h($site) ?></a>
    <div class="card-icon invalid">⚠️</div>
    <h1>Link expired</h1>
    <p>This password reset link is invalid or has already been used. Reset links expire after 1 hour.</p>
    <a href="/forgot.php" class="btn-submit" style="display:block;text-align:center;text-decoration:none;padding:15px;">Request a new link →</a>
    <div class="back-link"><a href="/login.php">← Back to sign in</a></div>
  </div>

<?php elseif ($state === 'success'): ?>

  <div class="card">
    <a href="/" class="logo"><?= h($site) ?></a>
    <div class="card-icon success">✓</div>
    <h1>Password updated</h1>
    <p>Your password has been changed and you're now signed in. Welcome back.</p>
    <a href="/app.php" class="btn-submit" style="display:block;text-align:center;text-decoration:none;padding:15px;">Continue to <?= h($site) ?> →</a>
  </div>

<?php else: ?>

  <div class="card">
    <a href="/" class="logo"><?= h($site) ?></a>
    <div class="card-icon lock">🔒</div>
    <h1>Choose a new password</h1>
    <p>Pick something strong — you won't be asked for your old password.</p>

    <?php if ($error): ?>
      <div class="error-msg">⚠ <?= h($error) ?></div>
    <?php endif ?>

    <form method="POST" id="resetForm" action="/reset.php?token=<?= urlencode($rawToken) ?>">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>"/>

      <div class="field" style="margin-bottom:6px">
        <label for="password">New password</label>
        <input
          type="password" id="password" name="password"
          required autocomplete="new-password"
          placeholder="At least 8 characters"
          minlength="8"
          oninput="checkStrength(this.value)"
        />
        <button type="button" class="toggle-pw" onclick="toggleVisibility('password', this)" aria-label="Show/hide password">👁</button>
      </div>
      <div class="strength-wrap" style="margin-bottom:22px">
        <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
        <div class="strength-label" id="strengthLabel"></div>
      </div>

      <div class="field" style="margin-bottom:28px">
        <label for="password2">Confirm password</label>
        <input
          type="password" id="password2" name="password2"
          required autocomplete="new-password"
          placeholder="Same password again"
        />
        <button type="button" class="toggle-pw" onclick="toggleVisibility('password2', this)" aria-label="Show/hide password">👁</button>
      </div>

      <button class="btn-submit" type="submit" id="submitBtn">Set New Password →</button>
    </form>

    <div class="back-link"><a href="/login.php">← Back to sign in</a></div>
  </div>

  <script>
  function toggleVisibility(id, btn) {
    const input = document.getElementById(id);
    input.type = input.type === 'password' ? 'text' : 'password';
    btn.textContent = input.type === 'password' ? '👁' : '🙈';
  }

  function checkStrength(pw) {
    const fill  = document.getElementById('strengthFill');
    const label = document.getElementById('strengthLabel');
    let score = 0;
    if (pw.length >= 8)  score++;
    if (pw.length >= 12) score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;
    const levels = [
      { pct: '0%',   color: 'transparent', text: '' },
      { pct: '25%',  color: '#ef4444',     text: 'Weak' },
      { pct: '50%',  color: '#f97316',     text: 'Fair' },
      { pct: '75%',  color: '#eab308',     text: 'Good' },
      { pct: '100%', color: '#22c55e',     text: 'Strong' },
    ];
    const lv = pw.length === 0 ? levels[0] : levels[Math.min(score, 4)];
    fill.style.width     = lv.pct;
    fill.style.background = lv.color;
    label.textContent    = lv.text;
    label.style.color    = lv.color || 'rgba(240,237,232,0.45)';
  }

  document.getElementById('resetForm').addEventListener('submit', function(e) {
    const p1 = document.getElementById('password').value;
    const p2 = document.getElementById('password2').value;
    if (p1 !== p2) {
      e.preventDefault();
      // Show mismatch inline without full page reload
      let err = document.querySelector('.error-msg');
      if (!err) {
        err = document.createElement('div');
        err.className = 'error-msg';
        this.insertAdjacentElement('beforebegin', err);
      }
      err.textContent = '⚠ Passwords do not match.';
      return;
    }
    const btn = document.getElementById('submitBtn');
    btn.disabled    = true;
    btn.textContent = 'Updating…';
  });
  </script>

<?php endif ?>
</body>
</html>
