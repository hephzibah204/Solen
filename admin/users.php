<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/admin_layout.php';
require_admin();

// Actions
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) { flash('error','Invalid request.'); redirect('/admin/users.php'); }

    if ($action === 'edit' && $id) {
        $name  = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $plan  = $_POST['plan'] ?? 'free';
        $role  = $_POST['role'] ?? 'user';
        db_run("UPDATE users SET name=?, email=?, plan=?, role=?, updated_at=datetime('now') WHERE id=?",
            [$name, $email, $plan, $role, $id]);
        if (!empty($_POST['password']) && strlen($_POST['password']) >= 8) {
            db_run("UPDATE users SET password=? WHERE id=?", [password_hash($_POST['password'], PASSWORD_DEFAULT), $id]);
        }
        flash('success', 'User updated.');
        redirect('/admin/users.php');
    }

    if ($action === 'delete' && $id) {
        db_run("DELETE FROM users WHERE id=? AND role!='admin'", [$id]);
        flash('success', 'User deleted.');
        redirect('/admin/users.php');
    }
}

// Edit view
if ($action === 'edit' && $id) {
    $user = db_one("SELECT * FROM users WHERE id=?", [$id]);
    if (!$user) { flash('error','User not found.'); redirect('/admin/users.php'); }
    admin_head('Edit User'); admin_sidebar('users');
    ?>
    <div class="main">
      <div class="topbar">
        <div class="topbar-title">Edit User</div>
        <a href="/admin/users.php" class="btn btn-ghost btn-sm">← Back</a>
      </div>
      <div class="content" style="max-width:640px">
        <?php admin_flash() ?>
        <div class="card">
          <form method="POST">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>"/>
            <div class="form-grid" style="margin-bottom:16px">
              <div class="form-group">
                <label>Full Name</label>
                <input class="form-control" name="name" value="<?= h($user['name']) ?>" required/>
              </div>
              <div class="form-group">
                <label>Email Address</label>
                <input class="form-control" type="email" name="email" value="<?= h($user['email']) ?>" required/>
              </div>
              <div class="form-group">
                <label>Plan</label>
                <select class="form-control" name="plan">
                  <?php foreach (['free','pro','premium'] as $p): ?>
                    <option value="<?= $p ?>" <?= $user['plan']===$p?'selected':'' ?>><?= ucfirst($p) ?></option>
                  <?php endforeach ?>
                </select>
              </div>
              <div class="form-group">
                <label>Role</label>
                <select class="form-control" name="role">
                  <option value="user" <?= $user['role']==='user'?'selected':'' ?>>User</option>
                  <option value="admin" <?= $user['role']==='admin'?'selected':'' ?>>Admin</option>
                </select>
              </div>
              <div class="form-group">
                <label>New Password <span class="text-muted">(leave blank to keep)</span></label>
                <input class="form-control" type="password" name="password" minlength="8"/>
              </div>
              <div class="form-group">
                <label>Trial Ends</label>
                <input class="form-control" type="text" value="<?= h($user['trial_ends'] ?? '—') ?>" disabled/>
              </div>
            </div>
            <div style="display:flex;gap:10px;margin-top:8px">
              <button class="btn btn-primary">Save Changes</button>
              <a href="/admin/users.php" class="btn btn-ghost">Cancel</a>
              <?php if ($user['role'] !== 'admin'): ?>
                <a href="/admin/users.php?action=delete&id=<?= $id ?>" class="btn btn-danger" style="margin-left:auto"
                   onclick="return confirm('Delete this user and all their data?')">Delete User</a>
              <?php endif ?>
            </div>
          </form>
        </div>

        <!-- User stats -->
        <div class="card" style="margin-top:16px">
          <div class="card-header"><div class="card-title">User Activity</div></div>
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px">
            <?php
              $moodCount   = db_count('mood_logs','user_id=?',[$id]);
              $memCount    = db_count('coach_memory','user_id=?',[$id]);
              $coach       = db_one("SELECT * FROM coach_profiles WHERE user_id=?",[$id]);
            ?>
            <div style="text-align:center;padding:14px;background:var(--bg2);border-radius:10px">
              <div style="font-family:'Cormorant Garamond',serif;font-size:28px;color:var(--accent)"><?= $moodCount ?></div>
              <div class="text-muted text-sm">Mood Logs</div>
            </div>
            <div style="text-align:center;padding:14px;background:var(--bg2);border-radius:10px">
              <div style="font-family:'Cormorant Garamond',serif;font-size:28px;color:var(--accent)"><?= $memCount ?></div>
              <div class="text-muted text-sm">Memories</div>
            </div>
            <div style="text-align:center;padding:14px;background:var(--bg2);border-radius:10px">
              <div style="font-family:'Cormorant Garamond',serif;font-size:28px;color:var(--accent)"><?= $coach['day_streak'] ?? 0 ?></div>
              <div class="text-muted text-sm">Day Streak</div>
            </div>
          </div>
          <?php if ($coach): ?>
          <div style="margin-top:14px;padding:12px;background:var(--bg2);border-radius:10px;font-size:13px;color:var(--muted)">
            Coach: <strong style="color:var(--text)"><?= h($coach['coach_name'] ?? '—') ?></strong> &nbsp;·&nbsp;
            Purpose: <strong style="color:var(--text)"><?= h($coach['purpose'] ?? '—') ?></strong> &nbsp;·&nbsp;
            Tone: <strong style="color:var(--text)"><?= h($coach['tone'] ?? '—') ?></strong>
          </div>
          <?php endif ?>
        </div>
      </div>
    </div>
    </body></html>
    <?php exit;
}

// LIST VIEW
$page    = max(1,(int)($_GET['page']??1));
$search  = trim($_GET['q'] ?? '');
$filter  = $_GET['plan'] ?? '';
$perPage = 20;
$where   = '1'; $params = [];
if ($search) { $where .= " AND (name LIKE ? OR email LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($filter) { $where .= " AND plan=?"; $params[] = $filter; }
$total = db_count('users', $where, $params);
$pg    = paginate($total, $page, $perPage);
$users = db_query("SELECT * FROM users WHERE $where ORDER BY created_at DESC LIMIT ? OFFSET ?",
    array_merge($params, [$perPage, $pg['offset']]));

admin_head('Users'); admin_sidebar('users');
?>
<div class="main">
  <div class="topbar">
    <div class="topbar-title">Users <span class="text-muted text-sm">(<?= number_format($total) ?>)</span></div>
  </div>
  <div class="content">
    <?php admin_flash() ?>
    <!-- Filters -->
    <div style="display:flex;gap:10px;margin-bottom:18px;flex-wrap:wrap;align-items:center">
      <form method="GET" style="display:flex;gap:8px;flex:1">
        <input class="search-bar" name="q" placeholder="Search name or email…" value="<?= h($search) ?>"/>
        <select class="form-control" name="plan" style="width:140px">
          <option value="">All plans</option>
          <?php foreach (['free','pro','premium'] as $p): ?>
            <option value="<?= $p ?>" <?= $filter===$p?'selected':'' ?>><?= ucfirst($p) ?></option>
          <?php endforeach ?>
        </select>
        <button class="btn btn-ghost">Filter</button>
        <?php if ($search||$filter): ?><a href="/admin/users.php" class="btn btn-ghost">Clear</a><?php endif ?>
      </form>
    </div>

    <div class="table-wrap">
      <table>
        <thead><tr><th>Name</th><th>Email</th><th>Plan</th><th>Role</th><th>Streak</th><th>Joined</th><th>Last Login</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td><strong><?= h($u['name']) ?></strong></td>
          <td class="text-muted"><?= h($u['email']) ?></td>
          <td><?= plan_badge($u['plan']) ?></td>
          <td><span style="font-size:12px;color:<?= $u['role']==='admin'?'var(--gold)':'var(--muted)' ?>"><?= h($u['role']) ?></span></td>
          <td class="text-muted"><?= db_one("SELECT day_streak FROM coach_profiles WHERE user_id=?",[$u['id']])['day_streak'] ?? 0 ?>d</td>
          <td class="text-muted text-sm"><?= format_date($u['created_at']) ?></td>
          <td class="text-muted text-sm"><?= $u['last_login'] ? time_ago($u['last_login']) : '—' ?></td>
          <td><a href="/admin/users.php?action=edit&id=<?= $u['id'] ?>" class="btn btn-ghost btn-sm">Edit</a></td>
        </tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>

    <?php if ($pg['pages'] > 1): ?>
    <div class="pagination">
      <?php for ($i=1;$i<=$pg['pages'];$i++): ?>
        <a class="<?= $i===$page?'active':'' ?>" href="?page=<?= $i ?><?= $search?"&q=".urlencode($search):'' ?><?= $filter?"&plan=$filter":'' ?>"><?= $i ?></a>
      <?php endfor ?>
    </div>
    <?php endif ?>
  </div>
</div>
</body></html>
