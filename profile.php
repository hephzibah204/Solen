<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$user  = current_user();
$sub   = db_one("SELECT * FROM subscriptions WHERE user_id=? ORDER BY id DESC LIMIT 1", [$user['id']]);
$txns  = db_query("SELECT * FROM payment_transactions WHERE user_id=? ORDER BY id DESC LIMIT 50", [$user['id']]);
$site  = get_setting('site_name', 'Solen');
$flash = '';

// Plan definitions (matches upgrade.php + admin prices)
$planDefs = [
    'plus'    => ['label'=>'Plus',    'color'=>'#3b82f6', 'monthly'=>(float)(get_setting('price_plus_monthly','9.99')),    'yearly'=>(float)(get_setting('price_plus_yearly','79.00')),    'perks'=>['Memory across sessions','All coaching programs','Mood history']],
    'pro'     => ['label'=>'Pro',     'color'=>'#a78bfa', 'monthly'=>(float)(get_setting('price_pro_monthly','12.99')),     'yearly'=>(float)(get_setting('price_pro_yearly','99.00')),     'perks'=>['Everything in Plus','Emotional intelligence','Priority AI speed']],
    'premium' => ['label'=>'Premium', 'color'=>'#f59e0b', 'monthly'=>(float)(get_setting('price_premium_monthly','24.99')), 'yearly'=>(float)(get_setting('price_premium_yearly','179.00')), 'perks'=>['Everything in Pro','Voice sessions','Family sharing']],
];
$paystackPk = get_setting('paystack_pk') ?: (defined('PAYSTACK_PK') ? PAYSTACK_PK : '');

$tab = $_GET['tab'] ?? 'account';

// ── Handle POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $flash = ['type'=>'error','msg'=>'Invalid request. Please try again.'];
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_account') {
            $name  = trim($_POST['name']  ?? '');
            $email = trim($_POST['email'] ?? '');
            if (!$name || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $flash = ['type'=>'error','msg'=>'Please provide a valid name and email.'];
            } else {
                $existing = db_one("SELECT id FROM users WHERE email=? AND id!=?", [$email, $user['id']]);
                if ($existing) {
                    $flash = ['type'=>'error','msg'=>'That email is already in use.'];
                } else {
                    db_run("UPDATE users SET name=?, email=? WHERE id=?", [$name, $email, $user['id']]);
                    $flash = ['type'=>'success','msg'=>'Account updated successfully.'];
                    $user['name']  = $name;
                    $user['email'] = $email;
                }
            }
        }

        if ($action === 'update_password') {
            $current = $_POST['current_password'] ?? '';
            $new     = $_POST['new_password']     ?? '';
            $confirm = $_POST['confirm_password'] ?? '';
            if (!password_verify($current, $user['password'])) {
                $flash = ['type'=>'error','msg'=>'Current password is incorrect.'];
            } elseif (strlen($new) < 8) {
                $flash = ['type'=>'error','msg'=>'New password must be at least 8 characters.'];
            } elseif ($new !== $confirm) {
                $flash = ['type'=>'error','msg'=>'Passwords do not match.'];
            } else {
                db_run("UPDATE users SET password=? WHERE id=?", [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
                $flash = ['type'=>'success','msg'=>'Password changed successfully.'];
            }
        }
    }
}

$firstName = explode(' ', $user['name'])[0];
$planLabels = ['free'=>'Free Trial','plus'=>'Plus','pro'=>'Pro','premium'=>'Premium'];
$planColors = ['free'=>'#6b7280','plus'=>'#3b82f6','pro'=>'#a78bfa','premium'=>'#f59e0b'];
$planLabel  = $planLabels[$user['plan']] ?? ucfirst($user['plan']);
$planColor  = $planColors[$user['plan']] ?? '#6b7280';

$trialLeft = null;
if ($user['plan'] === 'free' && $user['trial_ends']) {
    $trialLeft = max(0, ceil((strtotime($user['trial_ends']) - time()) / 86400));
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>My Profile — <?= h($site) ?></title>
<?php require_once __DIR__ . '/includes/pwa.php'; pwa_head(); ?>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#07070f;--surface:#0c0c1a;--surface2:#111128;
  --border:rgba(255,255,255,0.07);--border2:rgba(255,255,255,0.04);
  --accent:#b8956a;--accent2:rgba(184,149,106,0.12);
  --text:#f2ede8;--muted:rgba(242,237,232,0.42);
  --green:#22c55e;--red:#ef4444;
}
body{background:var(--bg);color:var(--text);font-family:'Outfit',sans-serif;min-height:100vh}
a{text-decoration:none}
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
.btn-danger{background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);color:#fca5a5}
.btn-danger:hover{background:rgba(239,68,68,0.22)}
.wrap{max-width:860px;margin:0 auto;padding:36px 28px 80px}
.profile-header{display:flex;align-items:center;gap:24px;margin-bottom:36px;flex-wrap:wrap}
.avatar-ring{width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,var(--accent),#7c3aed);display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:700;color:#fff;flex-shrink:0;box-shadow:0 0 24px rgba(184,149,106,0.3)}
.profile-info h1{font-family:'Playfair Display',serif;font-size:clamp(22px,3vw,30px);font-weight:400}
.profile-info p{color:var(--muted);font-size:14px;margin-top:4px}
.plan-badge{display:inline-flex;align-items:center;gap:6px;padding:4px 14px;border-radius:50px;font-size:12px;font-weight:600;margin-top:8px}
.tabs{display:flex;gap:0;border-bottom:1px solid var(--border);margin-bottom:28px;overflow-x:auto}
.tab-btn{padding:12px 20px;font-size:13px;font-weight:500;color:var(--muted);border:none;background:transparent;cursor:pointer;border-bottom:2px solid transparent;white-space:nowrap;transition:all 0.2s;font-family:'Outfit',sans-serif}
.tab-btn.active{color:var(--accent);border-bottom-color:var(--accent)}
.tab-btn:hover:not(.active){color:var(--text)}
.card{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:28px;margin-bottom:20px;position:relative;overflow:hidden}
.card::before{content:'';position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,rgba(255,255,255,0.06),transparent)}
.card-title{font-size:11px;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;color:var(--muted);margin-bottom:20px}
.form-group{margin-bottom:18px}
.form-group label{display:block;font-size:12px;color:var(--muted);margin-bottom:7px;letter-spacing:0.04em;text-transform:uppercase;font-weight:500}
.form-control{width:100%;background:rgba(255,255,255,0.04);border:1px solid var(--border);border-radius:12px;padding:12px 16px;color:var(--text);font-family:'Outfit',sans-serif;font-size:14px;transition:border 0.2s}
.form-control:focus{outline:none;border-color:rgba(184,149,106,0.5);background:rgba(255,255,255,0.06)}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.alert{padding:14px 18px;border-radius:12px;font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:10px}
.alert-success{background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.25);color:#86efac}
.alert-error{background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.25);color:#fca5a5}
.divider{height:1px;background:var(--border);margin:24px 0}
.billing-row{display:flex;align-items:center;justify-content:space-between;padding:14px 0;border-bottom:1px solid var(--border2)}
.billing-row:last-child{border-bottom:none}
.billing-label{font-size:13px;color:var(--muted)}
.billing-value{font-size:14px;font-weight:500}
.info-box{background:rgba(184,149,106,0.06);border:1px solid rgba(184,149,106,0.15);border-radius:12px;padding:16px;font-size:13px;color:var(--muted);line-height:1.6}
.info-box strong{color:var(--accent)}
/* Plan cards */
.plan-cards{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:24px}
.plan-card{background:var(--surface);border:1px solid var(--border);border-radius:18px;padding:22px;position:relative;transition:all 0.2s;cursor:pointer}
.plan-card.current{border-color:var(--accent);box-shadow:0 0 0 1px var(--accent)}
.plan-card:hover:not(.current){border-color:rgba(255,255,255,0.15);transform:translateY(-2px)}
.plan-card .pc-name{font-family:'Playfair Display',serif;font-size:18px;margin-bottom:4px}
.plan-card .pc-price{font-size:28px;font-weight:300;margin:10px 0 4px}
.plan-card .pc-period{font-size:12px;color:var(--muted)}
.plan-card .pc-perks{margin-top:14px;display:flex;flex-direction:column;gap:6px}
.plan-card .pc-perk{font-size:12px;color:var(--muted);display:flex;align-items:center;gap:6px}
.plan-card .pc-perk::before{content:'✓';color:var(--green);font-weight:700;flex-shrink:0}
.plan-card .pc-btn{margin-top:18px;width:100%;padding:11px;border-radius:50px;border:none;font-family:'Outfit',sans-serif;font-size:13px;font-weight:600;cursor:pointer;transition:all 0.2s}
.billing-toggle{display:flex;gap:0;background:rgba(255,255,255,0.04);border-radius:10px;padding:4px;width:fit-content;margin-bottom:22px}
.billing-toggle button{padding:8px 20px;border:none;background:transparent;color:var(--muted);border-radius:8px;font-family:'Outfit',sans-serif;font-size:13px;cursor:pointer;transition:all 0.2s}
.billing-toggle button.active{background:var(--accent);color:#1a1008;font-weight:600}
/* Transactions table */
.tx-table{width:100%;border-collapse:collapse;font-size:13px}
.tx-table th{text-align:left;padding:10px 14px;font-size:10px;letter-spacing:0.1em;text-transform:uppercase;color:var(--muted);border-bottom:1px solid var(--border);font-weight:600}
.tx-table td{padding:13px 14px;border-bottom:1px solid var(--border2);vertical-align:middle}
.tx-table tr:last-child td{border-bottom:none}
.tx-badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:50px;font-size:11px;font-weight:600}
.tx-success{background:rgba(34,197,94,0.12);color:#86efac}
.tx-pending{background:rgba(234,179,8,0.12);color:#fde047}
.tx-failed{background:rgba(239,68,68,0.12);color:#fca5a5}
@media(max-width:700px){.plan-cards{grid-template-columns:1fr}}
@media(max-width:600px){.form-grid{grid-template-columns:1fr}.profile-header{flex-direction:column;text-align:center}.wrap{padding:24px 16px 60px}.tx-table th:nth-child(3),.tx-table td:nth-child(3){display:none}}
</style>
</head>
<body>
<nav>
  <div class="nav-in">
    <a href="/" class="logo"><div class="logo-dot"></div><?= h($site) ?></a>
    <div class="nav-right">
      <a href="/dashboard.php" class="btn btn-ghost">← Dashboard</a>
      <a href="/app.php" class="btn btn-gold">Open Coach</a>
    </div>
  </div>
</nav>

<div class="wrap">

  <!-- Profile Header -->
  <div class="profile-header">
    <div class="avatar-ring"><?= mb_strtoupper(mb_substr($firstName, 0, 1)) ?></div>
    <div class="profile-info">
      <h1><?= h($user['name']) ?></h1>
      <p><?= h($user['email']) ?> · Member since <?= date('F Y', strtotime($user['created_at'])) ?></p>
      <div class="plan-badge" style="background:<?= $planColor ?>22;border:1px solid <?= $planColor ?>44;color:<?= $planColor ?>">
        ⭐ <?= $planLabel ?> Plan
      </div>
    </div>
  </div>

  <!-- Flash message -->
  <?php if ($flash): ?>
  <div class="alert alert-<?= $flash['type'] ?>">
    <?= $flash['type']==='success' ? '✅' : '❌' ?> <?= h($flash['msg']) ?>
  </div>
  <?php endif ?>

  <!-- Tabs -->
  <div class="tabs">
    <button class="tab-btn <?= $tab==='account'?'active':'' ?>" onclick="switchTab('account')">Account</button>
    <button class="tab-btn <?= $tab==='billing'?'active':'' ?>" onclick="switchTab('billing')">Billing</button>
    <button class="tab-btn <?= $tab==='security'?'active':'' ?>" onclick="switchTab('security')">Security</button>
  </div>

  <!-- ── ACCOUNT TAB ── -->
  <div id="tab-account" class="tab-content" style="display:<?= $tab==='account'?'block':'none' ?>">
    <form method="POST">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>"/>
      <input type="hidden" name="action" value="update_account"/>
      <div class="card">
        <div class="card-title">Personal Information</div>
        <div class="form-grid">
          <div class="form-group">
            <label>Full Name</label>
            <input class="form-control" name="name" value="<?= h($user['name']) ?>" required/>
          </div>
          <div class="form-group">
            <label>Email Address</label>
            <input class="form-control" type="email" name="email" value="<?= h($user['email']) ?>" required/>
          </div>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <button type="submit" class="btn btn-gold">Save Changes</button>
        </div>
      </div>
    </form>

    <div class="card">
      <div class="card-title">Coaching Preferences</div>
      <?php $coach = db_one("SELECT * FROM coach_profiles WHERE user_id=?", [$user['id']]); ?>
      <?php if ($coach): ?>
      <div style="display:flex;flex-direction:column;gap:14px">
        <div class="billing-row">
          <span class="billing-label">Coach Name</span>
          <span class="billing-value"><?= h($coach['coach_name'] ?? '—') ?></span>
        </div>
        <div class="billing-row">
          <span class="billing-label">Purpose</span>
          <span class="billing-value"><?= ucfirst(h($coach['purpose'] ?? '—')) ?></span>
        </div>
        <div class="billing-row">
          <span class="billing-label">Tone</span>
          <span class="billing-value"><?= ucfirst(h($coach['tone'] ?? '—')) ?></span>
        </div>
        <div class="billing-row">
          <span class="billing-label">Day Streak</span>
          <span class="billing-value"><?= (int)($coach['day_streak'] ?? 0) ?> 🔥</span>
        </div>
        <div class="billing-row">
          <span class="billing-label">Growth Stage</span>
          <span class="billing-value"><?= ucfirst(h($coach['growth_stage'] ?? 'exploration')) ?></span>
        </div>
      </div>
      <div style="margin-top:20px">
        <a href="/app.php" class="btn btn-ghost">Open Coach to Update →</a>
      </div>
      <?php else: ?>
      <p style="color:var(--muted);font-size:14px">You haven't set up a coach yet.</p>
      <div style="margin-top:16px"><a href="/app.php" class="btn btn-gold">Set Up My Coach →</a></div>
      <?php endif ?>
    </div>
  </div>

  <!-- ── BILLING TAB ── -->
  <div id="tab-billing" class="tab-content" style="display:<?= $tab==='billing'?'block':'none' ?>">

    <!-- Current Plan Summary -->
    <div class="card">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <div>
          <div style="font-size:11px;letter-spacing:0.1em;text-transform:uppercase;color:var(--muted);margin-bottom:6px">Current Plan</div>
          <div style="font-family:'Playfair Display',serif;font-size:26px;color:<?= $planColor ?>"><?= $planLabel ?></div>
          <div style="font-size:13px;color:var(--muted);margin-top:4px">
            <?php if ($user['plan']==='free'): ?>
              <?php if ($trialLeft !== null): ?>Trial — <strong style="color:var(--accent)"><?= $trialLeft ?> day<?= $trialLeft!=1?'s':'' ?> left</strong><?php else: ?>Free plan<?php endif ?>
            <?php else: ?>
              <?= ucfirst($sub['billing_cycle'] ?? 'monthly') ?> billing
              <?php if ($sub && $sub['expires_at']): ?> · Renews <?= date('M j, Y', strtotime($sub['expires_at'])) ?><?php endif ?>
            <?php endif ?>
          </div>
        </div>
        <?php if ($sub): ?>
        <div style="text-align:right;font-size:12px;color:var(--muted)">
          <div>Amount paid</div>
          <div style="font-size:22px;font-weight:300;color:var(--text)">$<?= number_format((float)($sub['amount']??0),2) ?></div>
          <div>per <?= $sub['billing_cycle']==='yearly'?'year':'month' ?></div>
        </div>
        <?php endif ?>
      </div>
    </div>

    <!-- Change Plan -->
    <div class="card">
      <div class="card-title">Change Plan</div>

      <div class="billing-toggle">
        <button id="tgl-monthly" class="active" onclick="setBillingMode('monthly')">Monthly</button>
        <button id="tgl-yearly" onclick="setBillingMode('yearly')">Yearly <span style="opacity:.5;font-size:11px">save 36%</span></button>
      </div>

      <div class="plan-cards" id="plan-cards-wrap">
        <?php foreach ($planDefs as $pKey => $pd): ?>
        <?php $isCurrent = ($user['plan'] === $pKey); ?>
        <div class="plan-card <?= $isCurrent?'current':'' ?>" id="pcard-<?= $pKey ?>">
          <?php if ($isCurrent): ?>
          <div style="position:absolute;top:14px;right:14px;font-size:10px;font-weight:700;letter-spacing:0.08em;color:var(--accent);text-transform:uppercase">Current</div>
          <?php endif ?>
          <div class="pc-name" style="color:<?= $pd['color'] ?>"><?= $pd['label'] ?></div>
          <div class="pc-price" data-monthly="<?= $pd['monthly'] ?>" data-yearly="<?= $pd['yearly'] ?>">$<?= number_format($pd['monthly'],2) ?></div>
          <div class="pc-period">per month</div>
          <div class="pc-perks">
            <?php foreach ($pd['perks'] as $perk): ?>
            <div class="pc-perk"><?= h($perk) ?></div>
            <?php endforeach ?>
          </div>
          <?php if (!$isCurrent): ?>
          <button class="pc-btn" style="background:<?= $pd['color'] ?>;color:#0a0a14"
            onclick="startUpgrade('<?= $pKey ?>')">
            <?= in_array($user['plan'],['plus','pro','premium']) && array_search($pKey,array_keys($planDefs)) < array_search($user['plan'],array_keys($planDefs)) ? 'Downgrade' : 'Upgrade' ?> to <?= $pd['label'] ?>
          </button>
          <?php else: ?>
          <button class="pc-btn" style="background:rgba(255,255,255,0.06);color:var(--muted);cursor:default" disabled>Active Plan</button>
          <?php endif ?>
        </div>
        <?php endforeach ?>
      </div>

      <div id="pay-error" style="display:none" class="alert alert-error"></div>
      <div id="pay-loading" style="display:none;text-align:center;padding:20px;color:var(--muted);font-size:13px">⏳ Connecting to Paystack...</div>
    </div>

    <!-- Transaction History -->
    <div class="card">
      <div class="card-title">Transaction History</div>
      <?php if (empty($txns)): ?>
      <div style="text-align:center;padding:32px 0;color:var(--muted);font-size:14px">
        <div style="font-size:36px;margin-bottom:12px">📋</div>
        No transactions yet. Upgrade to see your billing history.
      </div>
      <?php else: ?>
      <div style="overflow-x:auto">
      <table class="tx-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Plan</th>
            <th>Gateway</th>
            <th>Amount</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($txns as $tx): ?>
          <?php
            $statusClass = match($tx['status']) {
              'success','completed','active' => 'tx-success',
              'pending' => 'tx-pending',
              default   => 'tx-failed',
            };
            $statusLabel = match($tx['status']) {
              'success','completed','active' => '✅ Success',
              'pending' => '⏳ Pending',
              default   => '❌ ' . ucfirst($tx['status']),
            };
          ?>
          <tr>
            <td style="color:var(--muted)"><?= date('M j, Y', strtotime($tx['created_at'])) ?></td>
            <td><strong><?= ucfirst(h($tx['plan'])) ?></strong> <span style="font-size:11px;color:var(--muted)"><?= ucfirst($tx['billing']??'monthly') ?></span></td>
            <td style="color:var(--muted);text-transform:capitalize"><?= h($tx['gateway']) ?></td>
            <td style="font-weight:500">$<?= number_format((float)($tx['amount']??0),2) ?></td>
            <td><span class="tx-badge <?= $statusClass ?>"><?= $statusLabel ?></span></td>
          </tr>
          <?php endforeach ?>
        </tbody>
      </table>
      </div>
      <?php endif ?>
    </div>

  </div>

  <!-- Paystack Inline Script (loaded once, used when needed) -->
  <?php if ($paystackPk): ?>
  <script src="https://js.paystack.co/v1/inline.js"></script>
  <?php endif ?>

  <!-- ── SECURITY TAB ── -->
  <div id="tab-security" class="tab-content" style="display:<?= $tab==='security'?'block':'none' ?>">
    <form method="POST">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>"/>
      <input type="hidden" name="action" value="update_password"/>
      <div class="card">
        <div class="card-title">Change Password</div>
        <div class="form-group">
          <label>Current Password</label>
          <input class="form-control" type="password" name="current_password" required autocomplete="current-password"/>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label>New Password</label>
            <input class="form-control" type="password" name="new_password" required minlength="8" autocomplete="new-password"/>
          </div>
          <div class="form-group">
            <label>Confirm Password</label>
            <input class="form-control" type="password" name="confirm_password" required minlength="8" autocomplete="new-password"/>
          </div>
        </div>
        <button type="submit" class="btn btn-gold">Change Password</button>
      </div>
    </form>

    <div class="card">
      <div class="card-title">Session & Privacy</div>
      <div style="display:flex;flex-direction:column;gap:14px">
        <div class="billing-row">
          <span class="billing-label">Last Login</span>
          <span class="billing-value"><?= $user['last_login'] ? date('M j, Y H:i', strtotime($user['last_login'])) : 'Now' ?></span>
        </div>
        <div class="billing-row">
          <span class="billing-label">Account Role</span>
          <span class="billing-value"><?= ucfirst($user['role']) ?></span>
        </div>
      </div>
      <div class="divider"></div>
      <div style="display:flex;gap:12px;flex-wrap:wrap">
        <a href="/logout.php" class="btn btn-danger">Sign Out</a>
        <a href="/privacy.php" class="btn btn-ghost">Privacy Policy</a>
      </div>
    </div>
  </div>

</div>

<?php pwa_body(); ?>
<script>
const PAYSTACK_PK = <?= json_encode($paystackPk) ?>;
const USER_EMAIL  = <?= json_encode($user['email']) ?>;
const PLAN_DEFS   = <?= json_encode($planDefs) ?>;
let billingMode   = 'monthly';

function switchTab(name) {
  document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
  document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
  document.getElementById('tab-' + name).style.display = 'block';
  event.currentTarget.classList.add('active');
  history.replaceState(null,'','?tab=' + name);
}

function setBillingMode(mode) {
  billingMode = mode;
  document.getElementById('tgl-monthly').classList.toggle('active', mode==='monthly');
  document.getElementById('tgl-yearly').classList.toggle('active',  mode==='yearly');
  document.querySelectorAll('.pc-price').forEach(el => {
    const price = mode === 'yearly' ? parseFloat(el.dataset.yearly) : parseFloat(el.dataset.monthly);
    el.textContent = '$' + price.toFixed(2);
    el.nextElementSibling.textContent = mode === 'yearly' ? 'per year' : 'per month';
  });
}

async function startUpgrade(plan) {
  const errBox  = document.getElementById('pay-error');
  const loading = document.getElementById('pay-loading');
  errBox.style.display  = 'none';
  loading.style.display = 'block';

  // If Paystack key is present, use inline checkout
  if (PAYSTACK_PK) {
    loading.style.display = 'none';
    const pd     = PLAN_DEFS[plan];
    const amount = billingMode === 'yearly' ? pd.yearly : pd.monthly;
    const amountKobo = Math.round(amount * 100); // Paystack expects smallest currency unit

    // Initialize Paystack inline
    try {
      const handler = PaystackPop.setup({
        key:       PAYSTACK_PK,
        email:     USER_EMAIL,
        amount:    amountKobo,
        currency:  'USD',
        ref:       'solen_' + Date.now(),
        metadata: { plan, billing: billingMode, custom_fields: [{display_name:'Plan',variable_name:'plan',value:plan}] },
        onClose: () => { loading.style.display = 'none'; },
        callback: async (response) => {
          loading.style.display = 'block';
          loading.textContent   = '✅ Payment received — activating your plan...';
          try {
            const res  = await fetch('/api/payment.php', {
              method: 'POST',
              headers: {'Content-Type':'application/json'},
              body: JSON.stringify({action:'verify', gateway:'paystack', reference: response.reference, plan, billing: billingMode})
            });
            const data = await res.json();
            if (data.status === 'success') {
              window.location.href = '/upgrade-success.php?status=success&gateway=paystack';
            } else {
              throw new Error(data.error || 'Verification failed');
            }
          } catch(e) {
            loading.style.display = 'none';
            errBox.textContent = '⚠️ ' + e.message + ' — contact support if charged.';
            errBox.style.display = 'flex';
          }
        }
      });
      handler.openIframe();
    } catch(e) {
      loading.style.display = 'none';
      errBox.textContent = 'Paystack not loaded. Please refresh and try again.';
      errBox.style.display = 'flex';
    }
    return;
  }

  // Fallback: server-side redirect (Paystack hosted checkout)
  try {
    const res  = await fetch('/api/payment.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({action:'initiate', gateway:'paystack', plan, billing: billingMode})
    });
    const data = await res.json();
    if (data.url) { window.location.href = data.url; }
    else { throw new Error(data.error || 'Payment init failed'); }
  } catch(e) {
    loading.style.display  = 'none';
    errBox.textContent = '⚠️ ' + e.message;
    errBox.style.display = 'flex';
  }
}
</script>
</body>
</html>
