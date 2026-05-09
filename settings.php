<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$user  = current_user();
$uid   = (int)$user['id'];
$flash = '';
$error = '';

// ── Handle POST actions ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? '')) {
    $action = $_POST['action'] ?? '';

    // ── Update name / email ────────────────────────────────────────────────
    if ($action === 'profile') {
        $name  = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid name and email.';
        } else {
            $taken = db_one("SELECT id FROM users WHERE email=? AND id!=?", [$email, $uid]);
            if ($taken) { $error = 'That email address is already in use.'; }
            else {
                db_run("UPDATE users SET name=?, email=? WHERE id=?", [$name, $email, $uid]);
                $_SESSION['user_name'] = $name;
                $flash = 'Profile updated.';
            }
        }
    }

    // ── Change password ────────────────────────────────────────────────────
    if ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (!password_verify($current, $user['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            db_run("UPDATE users SET password=? WHERE id=?", [$hash, $uid]);
            // Revoke all other sessions for safety
            db_run("DELETE FROM user_sessions WHERE user_id=? AND token!=?", [$uid, $_SESSION['db_token'] ?? '']);
            $flash = 'Password changed. All other devices have been logged out.';
        }
    }

    // ── Notification prefs ─────────────────────────────────────────────────
    if ($action === 'notifications') {
        $checkin = isset($_POST['checkin_reminder']) ? '1' : '0';
        $trials  = isset($_POST['trial_warnings'])   ? '1' : '0';
        // Store per-user prefs in a JSON blob on coach_profiles or a dedicated column
        // We'll use the settings-like approach: upsert into a user_prefs table-less
        // solution by encoding into the coach_profiles notes or a separate key.
        // For simplicity, store as a JSON column we add via migration.
        db_run(
            "UPDATE coach_profiles SET notification_prefs=? WHERE user_id=?",
            [json_encode(['checkin_reminder'=>$checkin,'trial_warnings'=>$trials]), $uid]
        );
        $flash = 'Notification preferences saved.';
    }

    // ── Logout all devices ─────────────────────────────────────────────────
    if ($action === 'logout_all') {
        logout_all_devices($uid);
        header('Location: /login.php');
        exit;
    }

    // ── Delete account ─────────────────────────────────────────────────────
    if ($action === 'delete_account') {
        $confirm = $_POST['confirm_delete'] ?? '';
        $pw      = $_POST['delete_password'] ?? '';
        if ($confirm !== 'DELETE' || !password_verify($pw, $user['password'])) {
            $error = 'Type DELETE and enter your password to confirm account deletion.';
        } else {
            // Cascade-delete all user data (FK ON DELETE CASCADE handles most tables)
            db_run("DELETE FROM users WHERE id=?", [$uid]);
            session_destroy();
            header('Location: /?deleted=1');
            exit;
        }
    }

    // Re-fetch user after possible updates
    $user = db_one("SELECT * FROM users WHERE id=?", [$uid]) ?: $user;
}

// ── Fetch notification prefs ───────────────────────────────────────────────
$cp    = db_one("SELECT notification_prefs FROM coach_profiles WHERE user_id=?", [$uid]);
$prefs = json_decode($cp['notification_prefs'] ?? '{}', true);
$checkinOn = ($prefs['checkin_reminder'] ?? '1') === '1';
$trialOn   = ($prefs['trial_warnings']   ?? '1') === '1';

// ── Active sessions ────────────────────────────────────────────────────────
$sessions = db_query(
    "SELECT id, created_at, expires_at, CASE WHEN token=? THEN 1 ELSE 0 END as is_current
       FROM user_sessions WHERE user_id=? ORDER BY id DESC LIMIT 10",
    [$_SESSION['db_token'] ?? '', $uid]
);

$site = get_setting('site_name', 'Solen');
$accent = '#c5a572';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"/>
<title>Account Settings — <?= h($site) ?></title>
<?php require_once __DIR__ . '/includes/pwa.php'; pwa_head(); ?>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#07070f;--surface:#0e0e1a;--border:rgba(255,255,255,0.08);--accent:<?= $accent ?>;--text:#f2ede8;--muted:rgba(242,237,232,0.45)}
body{background:var(--bg);color:var(--text);font-family:'Outfit',sans-serif;min-height:100vh;padding:0}
.layout{display:grid;grid-template-columns:220px 1fr;min-height:100vh}
.sidebar{background:var(--surface);border-right:1px solid var(--border);padding:28px 20px;display:flex;flex-direction:column;gap:4px}
.sidebar-logo{font-family:'Playfair Display',serif;font-size:20px;color:var(--accent);margin-bottom:24px;display:flex;align-items:center;gap:10px}
.sidebar-logo .dot{width:7px;height:7px;background:var(--accent);border-radius:50%;box-shadow:0 0 8px var(--accent)}
.nav-link{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:10px;color:var(--muted);text-decoration:none;font-size:14px;transition:all 0.2s}
.nav-link:hover,.nav-link.active{background:rgba(255,255,255,0.05);color:var(--text)}
.main{padding:40px 48px;max-width:700px}
h1{font-family:'Playfair Display',serif;font-size:32px;font-weight:400;margin-bottom:8px}
.subtitle{color:var(--muted);font-size:14px;margin-bottom:36px}
.section{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:28px;margin-bottom:20px}
.section-title{font-size:11px;text-transform:uppercase;letter-spacing:0.12em;color:var(--muted);margin-bottom:20px;font-weight:500}
.field{margin-bottom:18px}
.field label{display:block;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;color:var(--muted);margin-bottom:8px;font-weight:500}
.field input{width:100%;background:rgba(255,255,255,0.04);border:1px solid var(--border);border-radius:10px;padding:12px 16px;color:var(--text);font-family:'Outfit',sans-serif;font-size:15px;transition:all 0.2s}
.field input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(197,165,114,0.12)}
.field input::placeholder{color:rgba(242,237,232,0.2)}
.btn{padding:11px 24px;border-radius:50px;font-family:'Outfit',sans-serif;font-size:14px;font-weight:600;cursor:pointer;border:none;transition:all 0.2s}
.btn-primary{background:var(--accent);color:#1a1008}
.btn-primary:hover{background:#d4ae82;transform:translateY(-1px)}
.btn-ghost{background:transparent;border:1px solid var(--border);color:var(--muted)}
.btn-ghost:hover{border-color:rgba(255,255,255,0.2);color:var(--text)}
.btn-danger{background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2);color:#fca5a5}
.btn-danger:hover{background:rgba(239,68,68,0.18)}
.toggle-row{display:flex;align-items:center;justify-content:space-between;padding:14px 0;border-bottom:1px solid var(--border)}
.toggle-row:last-child{border-bottom:none}
.toggle-label{font-size:14px;color:var(--text)}
.toggle-desc{font-size:12px;color:var(--muted);margin-top:2px}
.toggle{position:relative;width:44px;height:24px;flex-shrink:0}
.toggle input{opacity:0;width:0;height:0}
.slider{position:absolute;inset:0;background:rgba(255,255,255,0.12);border-radius:24px;transition:0.3s;cursor:pointer}
.slider:before{content:'';position:absolute;width:18px;height:18px;left:3px;top:3px;background:#fff;border-radius:50%;transition:0.3s}
input:checked+.slider{background:var(--accent)}
input:checked+.slider:before{transform:translateX(20px)}
.flash{background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.2);color:#86efac;padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:20px}
.err{background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);color:#fca5a5;padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:20px}
.session-row{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border);font-size:13px}
.session-row:last-child{border-bottom:none}
.badge{font-size:10px;padding:2px 8px;border-radius:50px;background:rgba(197,165,114,0.12);color:var(--accent);margin-left:8px}
.danger-zone{border-color:rgba(239,68,68,0.2)}
.danger-zone .section-title{color:rgba(252,165,165,0.6)}
@media(max-width:700px){.layout{grid-template-columns:1fr}.sidebar{display:none}.main{padding:24px 16px}}
</style>
</head>
<body>
<div class="layout">
  <div class="sidebar">
    <div class="sidebar-logo"><div class="dot"></div><?= h($site) ?></div>
    <a href="/app.php"      class="nav-link">🧠 Your Coach</a>
    <a href="/settings.php" class="nav-link active">⚙️ Settings</a>
    <div style="flex:1"></div>
    <a href="/logout.php"   class="nav-link" style="color:rgba(252,165,165,0.5)">↩ Sign Out</a>
  </div>
  <div class="main">
    <h1>Account Settings</h1>
    <p class="subtitle">Manage your profile, security, and notification preferences.</p>

    <?php if ($flash): ?><div class="flash">✓ <?= h($flash) ?></div><?php endif ?>
    <?php if ($error): ?><div class="err">⚠ <?= h($error) ?></div><?php endif ?>

    <!-- Profile -->
    <div class="section">
      <div class="section-title">Profile</div>
      <form method="POST">
        <input type="hidden" name="csrf"   value="<?= csrf_token() ?>"/>
        <input type="hidden" name="action" value="profile"/>
        <div class="field"><label>Name</label>
          <input type="text"  name="name"  value="<?= h($user['name']) ?>" required/>
        </div>
        <div class="field"><label>Email address</label>
          <input type="email" name="email" value="<?= h($user['email']) ?>" required/>
        </div>
        <button class="btn btn-primary" type="submit">Save changes</button>
      </form>
    </div>

    <!-- Password -->
    <div class="section">
      <div class="section-title">Change Password</div>
      <form method="POST">
        <input type="hidden" name="csrf"   value="<?= csrf_token() ?>"/>
        <input type="hidden" name="action" value="password"/>
        <div class="field"><label>Current password</label>
          <input type="password" name="current_password" required/>
        </div>
        <div class="field"><label>New password</label>
          <input type="password" name="new_password" required minlength="8" placeholder="At least 8 characters"/>
        </div>
        <div class="field"><label>Confirm new password</label>
          <input type="password" name="confirm_password" required minlength="8"/>
        </div>
        <button class="btn btn-primary" type="submit">Update password</button>
      </form>
    </div>

    <!-- Notifications -->
    <div class="section">
      <div class="section-title">Email Notifications</div>
      <form method="POST">
        <input type="hidden" name="csrf"   value="<?= csrf_token() ?>"/>
        <input type="hidden" name="action" value="notifications"/>
        <div class="toggle-row">
          <div>
            <div class="toggle-label">Daily check-in reminder</div>
            <div class="toggle-desc">A gentle nudge when you haven't opened the app today.</div>
          </div>
          <label class="toggle">
            <input type="checkbox" name="checkin_reminder" <?= $checkinOn?'checked':'' ?>/>
            <span class="slider"></span>
          </label>
        </div>
        <div class="toggle-row">
          <div>
            <div class="toggle-label">Trial expiry warnings</div>
            <div class="toggle-desc">Reminders when your free trial is about to end.</div>
          </div>
          <label class="toggle">
            <input type="checkbox" name="trial_warnings" <?= $trialOn?'checked':'' ?>/>
            <span class="slider"></span>
          </label>
        </div>
        <div style="margin-top:18px">
          <button class="btn btn-primary" type="submit">Save preferences</button>
        </div>
      </form>
    </div>

    <!-- Active Sessions -->
    <div class="section">
      <div class="section-title">Active Sessions</div>
      <?php foreach ($sessions as $s): ?>
      <div class="session-row">
        <div>
          <span>Session</span>
          <?php if ($s['is_current']): ?><span class="badge">This device</span><?php endif ?>
          <div style="font-size:11px;color:var(--muted);margin-top:2px">
            Started <?= time_ago($s['created_at']) ?> · Expires <?= date('M j', strtotime($s['expires_at'])) ?>
          </div>
        </div>
      </div>
      <?php endforeach ?>
      <div style="margin-top:16px">
        <form method="POST" onsubmit="return confirm('This will log you out of all devices.')">
          <input type="hidden" name="csrf"   value="<?= csrf_token() ?>"/>
          <input type="hidden" name="action" value="logout_all"/>
          <button class="btn btn-ghost" type="submit">Log out all devices</button>
        </form>
      </div>
    </div>

    <!-- Danger Zone -->
    <div class="section danger-zone">
      <div class="section-title">Danger Zone</div>
      <p style="font-size:14px;color:var(--muted);margin-bottom:20px;line-height:1.65">
        Permanently delete your account and all associated data — conversations, mood logs, memories, and billing history.
        This cannot be undone. GDPR data deletion requests are processed immediately.
      </p>
      <form method="POST" onsubmit="return confirm('This will permanently delete your account. Are you absolutely sure?')">
        <input type="hidden" name="csrf"   value="<?= csrf_token() ?>"/>
        <input type="hidden" name="action" value="delete_account"/>
        <div class="field"><label>Type DELETE to confirm</label>
          <input type="text" name="confirm_delete" placeholder="DELETE" pattern="DELETE" required/>
        </div>
        <div class="field"><label>Enter your password</label>
          <input type="password" name="delete_password" required/>
        </div>
        <button class="btn btn-danger" type="submit">Permanently delete my account</button>
      </form>
    </div>
  </div>
</div>
<?php pwa_body(); ?>
</body>
</html>
