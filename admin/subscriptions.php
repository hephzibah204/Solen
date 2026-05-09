<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/admin_layout.php';
require_admin();

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) { flash('error','Invalid request.'); redirect('/admin/subscriptions.php'); }

    if ($action === 'edit' && $id) {
        db_run("UPDATE subscriptions SET plan=?, status=?, amount=?, billing_cycle=?, expires_at=?, notes=? WHERE id=?", [
            $_POST['plan'], $_POST['status'], (float)$_POST['amount'],
            $_POST['billing_cycle'], $_POST['expires_at'] ?: null,
            trim($_POST['notes'] ?? ''), $id
        ]);
        // Sync user plan
        $sub = db_one("SELECT * FROM subscriptions WHERE id=?", [$id]);
        db_run("UPDATE users SET plan=? WHERE id=?", [$sub['plan'], $sub['user_id']]);
        flash('success', 'Subscription updated.');
        redirect('/admin/subscriptions.php');
    }

    if ($action === 'cancel' && $id) {
        db_run("UPDATE subscriptions SET status='cancelled', cancelled_at=datetime('now') WHERE id=?", [$id]);
        flash('success', 'Subscription cancelled.');
        redirect('/admin/subscriptions.php');
    }

    if ($action === 'create') {
        $uid = (int)$_POST['user_id'];
        db_run("INSERT INTO subscriptions (user_id, plan, status, amount, billing_cycle, expires_at, notes) VALUES (?,?,?,?,?,?,?)", [
            $uid, $_POST['plan'], $_POST['status'], (float)$_POST['amount'],
            $_POST['billing_cycle'], $_POST['expires_at'] ?: null, trim($_POST['notes'] ?? '')
        ]);
        db_run("UPDATE users SET plan=? WHERE id=?", [$_POST['plan'], $uid]);
        flash('success', 'Subscription created.');
        redirect('/admin/subscriptions.php');
    }
}

// Edit form
if ($action === 'edit' && $id) {
    $sub  = db_one("SELECT s.*, u.name as user_name, u.email as user_email FROM subscriptions s JOIN users u ON s.user_id=u.id WHERE s.id=?", [$id]);
    admin_head('Edit Subscription'); admin_sidebar('subscriptions');
    ?>
    <div class="main">
      <div class="topbar"><div class="topbar-title">Edit Subscription</div><a href="/admin/subscriptions.php" class="btn btn-ghost btn-sm">← Back</a></div>
      <div class="content" style="max-width:600px">
        <?php admin_flash() ?>
        <div class="card">
          <div style="padding:14px;background:var(--bg2);border-radius:10px;margin-bottom:20px;font-size:13px">
            User: <strong><?= h($sub['user_name']) ?></strong> &nbsp;·&nbsp; <?= h($sub['user_email']) ?>
          </div>
          <form method="POST">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>"/>
            <div class="form-grid" style="margin-bottom:16px">
              <div class="form-group">
                <label>Plan</label>
                <select class="form-control" name="plan">
                  <?php foreach (['free','pro','premium'] as $p): ?>
                    <option value="<?= $p ?>" <?= $sub['plan']===$p?'selected':'' ?>><?= ucfirst($p) ?></option>
                  <?php endforeach ?>
                </select>
              </div>
              <div class="form-group">
                <label>Status</label>
                <select class="form-control" name="status">
                  <?php foreach (['active','trial','cancelled','expired'] as $s): ?>
                    <option value="<?= $s ?>" <?= $sub['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                  <?php endforeach ?>
                </select>
              </div>
              <div class="form-group">
                <label>Amount (USD)</label>
                <input class="form-control" type="number" step="0.01" name="amount" value="<?= $sub['amount'] ?>"/>
              </div>
              <div class="form-group">
                <label>Billing Cycle</label>
                <select class="form-control" name="billing_cycle">
                  <option value="monthly" <?= $sub['billing_cycle']==='monthly'?'selected':'' ?>>Monthly</option>
                  <option value="yearly"  <?= $sub['billing_cycle']==='yearly'?'selected':'' ?>>Yearly</option>
                </select>
              </div>
              <div class="form-group">
                <label>Expires At</label>
                <input class="form-control" type="datetime-local" name="expires_at" value="<?= $sub['expires_at'] ? date('Y-m-d\TH:i', strtotime($sub['expires_at'])) : '' ?>"/>
              </div>
              <div class="form-group">
                <label>Internal Notes</label>
                <input class="form-control" name="notes" value="<?= h($sub['notes'] ?? '') ?>"/>
              </div>
            </div>
            <div style="display:flex;gap:10px">
              <button class="btn btn-primary">Save Changes</button>
              <a href="/admin/subscriptions.php" class="btn btn-ghost">Cancel</a>
              <?php if ($sub['status'] !== 'cancelled'): ?>
                <a href="/admin/subscriptions.php?action=cancel&id=<?= $id ?>" class="btn btn-danger" style="margin-left:auto"
                   onclick="return confirm('Cancel this subscription?')">Cancel Subscription</a>
              <?php endif ?>
            </div>
          </form>
        </div>
      </div>
    </div>
    </body></html>
    <?php exit;
}

// New subscription form
if ($action === 'new') {
    $allUsers = db_query("SELECT id, name, email FROM users ORDER BY name");
    admin_head('New Subscription'); admin_sidebar('subscriptions');
    ?>
    <div class="main">
      <div class="topbar"><div class="topbar-title">Create Subscription</div><a href="/admin/subscriptions.php" class="btn btn-ghost btn-sm">← Back</a></div>
      <div class="content" style="max-width:600px">
        <?php admin_flash() ?>
        <div class="card">
          <form method="POST" action="/admin/subscriptions.php?action=create">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>"/>
            <div class="form-grid" style="margin-bottom:16px">
              <div class="form-group" style="grid-column:1/-1">
                <label>User</label>
                <select class="form-control" name="user_id" required>
                  <option value="">— Select user —</option>
                  <?php foreach ($allUsers as $u): ?>
                    <option value="<?= $u['id'] ?>"><?= h($u['name']) ?> (<?= h($u['email']) ?>)</option>
                  <?php endforeach ?>
                </select>
              </div>
              <div class="form-group">
                <label>Plan</label>
                <select class="form-control" name="plan">
                  <?php foreach (['free','pro','premium'] as $p): ?>
                    <option value="<?= $p ?>"><?= ucfirst($p) ?></option>
                  <?php endforeach ?>
                </select>
              </div>
              <div class="form-group">
                <label>Status</label>
                <select class="form-control" name="status">
                  <option value="active">Active</option>
                  <option value="trial">Trial</option>
                </select>
              </div>
              <div class="form-group">
                <label>Amount (USD / month)</label>
                <input class="form-control" type="number" step="0.01" name="amount" value="0.00"/>
              </div>
              <div class="form-group">
                <label>Billing Cycle</label>
                <select class="form-control" name="billing_cycle">
                  <option value="monthly">Monthly</option>
                  <option value="yearly">Yearly</option>
                </select>
              </div>
              <div class="form-group">
                <label>Expires At <span class="text-muted">(optional)</span></label>
                <input class="form-control" type="datetime-local" name="expires_at"/>
              </div>
              <div class="form-group">
                <label>Notes</label>
                <input class="form-control" name="notes" placeholder="e.g. Comped for review"/>
              </div>
            </div>
            <button class="btn btn-primary">Create Subscription</button>
          </form>
        </div>
      </div>
    </div>
    </body></html>
    <?php exit;
}

// LIST
$page   = max(1,(int)($_GET['page']??1));
$search = trim($_GET['q']??'');
$status = $_GET['status']??'';
$perPage = 20;

$where = '1'; $params = [];
if ($search) { $where .= " AND (u.name LIKE ? OR u.email LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($status) { $where .= " AND s.status=?"; $params[] = $status; }

$total = db_one("SELECT COUNT(*) as n FROM subscriptions s JOIN users u ON s.user_id=u.id WHERE $where", $params)['n'] ?? 0;
$pg    = paginate((int)$total, $page, $perPage);
$subs  = db_query("SELECT s.*, u.name as user_name, u.email as user_email FROM subscriptions s JOIN users u ON s.user_id=u.id WHERE $where ORDER BY s.started_at DESC LIMIT ? OFFSET ?",
    array_merge($params, [$perPage, $pg['offset']]));

$mrr   = db_one("SELECT COALESCE(SUM(s.amount),0) as t FROM subscriptions s WHERE s.status='active' AND s.billing_cycle='monthly'")['t'] ?? 0;
$arr   = db_one("SELECT COALESCE(SUM(s.amount),0) as t FROM subscriptions s WHERE s.status='active' AND s.billing_cycle='yearly'")['t'] ?? 0;

admin_head('Subscriptions'); admin_sidebar('subscriptions');
?>
<div class="main">
  <div class="topbar">
    <div class="topbar-title">Subscriptions</div>
    <a href="/admin/subscriptions.php?action=new" class="btn btn-primary btn-sm">+ New Subscription</a>
  </div>
  <div class="content">
    <?php admin_flash() ?>

    <!-- MRR Row -->
    <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px">
      <?php
      $sc = ['active'=>0,'trial'=>0,'cancelled'=>0,'expired'=>0];
      foreach (db_query("SELECT status, COUNT(*) as n FROM subscriptions GROUP BY status") as $r) $sc[$r['status']] = $r['n'];
      ?>
      <div class="stat-card"><div class="stat-label">MRR</div><div class="stat-value">$<?= number_format($mrr,0) ?></div><div class="stat-sub">Monthly recurring</div></div>
      <div class="stat-card"><div class="stat-label">ARR</div><div class="stat-value">$<?= number_format(($mrr*12)+$arr,0) ?></div><div class="stat-sub">Annual estimate</div></div>
      <div class="stat-card"><div class="stat-label">Active</div><div class="stat-value"><?= $sc['active'] ?></div><div class="stat-sub"><?= $sc['trial'] ?> trials</div></div>
      <div class="stat-card"><div class="stat-label">Churned</div><div class="stat-value"><?= $sc['cancelled'] ?></div><div class="stat-sub"><?= $sc['expired'] ?> expired</div></div>
    </div>

    <!-- Filters -->
    <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap">
      <form method="GET" style="display:flex;gap:8px">
        <input class="search-bar" name="q" placeholder="Search user…" value="<?= h($search) ?>"/>
        <select class="form-control" name="status" style="width:130px">
          <option value="">All statuses</option>
          <?php foreach (['active','trial','cancelled','expired'] as $s): ?>
            <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
          <?php endforeach ?>
        </select>
        <button class="btn btn-ghost">Filter</button>
        <?php if ($search||$status): ?><a href="/admin/subscriptions.php" class="btn btn-ghost">Clear</a><?php endif ?>
      </form>
    </div>

    <div class="table-wrap">
      <table>
        <thead><tr><th>User</th><th>Plan</th><th>Status</th><th>Amount</th><th>Cycle</th><th>Started</th><th>Expires</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($subs as $s): ?>
        <tr>
          <td>
            <strong><?= h($s['user_name']) ?></strong>
            <div class="text-muted text-sm"><?= h($s['user_email']) ?></div>
          </td>
          <td><?= plan_badge($s['plan']) ?></td>
          <td><?= status_badge($s['status']) ?></td>
          <td><?= $s['amount'] > 0 ? '$'.number_format($s['amount'],2) : '<span class="text-muted">Free</span>' ?></td>
          <td class="text-muted"><?= h($s['billing_cycle']) ?></td>
          <td class="text-muted text-sm"><?= format_date($s['started_at']) ?></td>
          <td class="text-muted text-sm"><?= $s['expires_at'] ? format_date($s['expires_at']) : '—' ?></td>
          <td><a href="/admin/subscriptions.php?action=edit&id=<?= $s['id'] ?>" class="btn btn-ghost btn-sm">Edit</a></td>
        </tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>

    <?php if ($pg['pages'] > 1): ?>
    <div class="pagination">
      <?php for ($i=1;$i<=$pg['pages'];$i++): ?>
        <a class="<?= $i===$page?'active':'' ?>" href="?page=<?= $i ?><?= $search?"&q=".urlencode($search):'' ?><?= $status?"&status=$status":'' ?>"><?= $i ?></a>
      <?php endfor ?>
    </div>
    <?php endif ?>
  </div>
</div>
</body></html>
