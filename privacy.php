<?php
/**
 * /privacy.php — Solen Privacy & Memory Dashboard (Phase 7)
 *
 * A premium, user-facing dashboard to manage:
 *   - Memory episodes (list, delete)
 *   - Active sessions (view, revoke)
 *   - Data export (JSON)
 *   - Privacy settings (memory toggle)
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/memory.php';

require_login();
$user   = current_user();
$userId = (int)$user['id'];

// ── HANDLE ACTIONS ────────────────────────────────────────────────────────
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $err = "Invalid request (CSRF)";
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'delete_memory') {
            $epId = (int)$_POST['episode_id'];
            if (memory_delete_episode($userId, $epId)) {
                $msg = "Memory deleted successfully.";
            } else {
                $err = "Could not delete memory.";
            }
        }

        if ($action === 'revoke_session') {
            $token = $_POST['token'] ?? '';
            if ($token === $_SESSION['db_token']) {
                $err = "You cannot revoke your current session here. Use 'Log Out' instead.";
            } else {
                db_run("DELETE FROM user_sessions WHERE user_id=? AND token=?", [$userId, $token]);
                $msg = "Session revoked successfully.";
            }
        }

        if ($action === 'revoke_all') {
            db_run("DELETE FROM user_sessions WHERE user_id=? AND token != ?", [$userId, $_SESSION['db_token']]);
            $msg = "All other sessions have been revoked.";
        }

        if ($action === 'update_settings') {
            $memEnabled = isset($_POST['memory_enabled']) ? '1' : '0';
            set_setting("user_{$userId}_memory_enabled", $memEnabled); // user-specific override
            $msg = "Privacy settings updated.";
        }
    }
}

// ── DATA FETCH ────────────────────────────────────────────────────────────
$sessions = db_query(
    "SELECT token, expires_at, created_at FROM user_sessions WHERE user_id=? ORDER BY created_at DESC",
    [$userId]
);

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$episodes = memory_get_user_episodes($userId, $page, $perPage);
$totalEps = db_count('memory_episodes', 'user_id=?', [$userId]);

$memoryEnabled = get_setting("user_{$userId}_memory_enabled", '1');

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy & Memory — Solen</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #07070f;
            --surface: #0d0d1e;
            --surface-light: #16162d;
            --border: rgba(255,255,255,0.08);
            --accent: #b8956a;
            --accent-soft: rgba(184,149,106,0.12);
            --text: #f2ede8;
            --muted: rgba(242,237,232,0.45);
            --danger: #ef4444;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Outfit', sans-serif;
            line-height: 1.6;
            padding-bottom: 80px;
        }

        .container { max-width: 800px; margin: 0 auto; padding: 40px 20px; }

        header { margin-bottom: 40px; display: flex; align-items: center; justify-content: space-between; }
        h1 { font-family: 'Playfair Display', serif; font-size: 32px; font-weight: 400; }
        .back-link { color: var(--muted); text-decoration: none; font-size: 14px; transition: color 0.2s; }
        .back-link:hover { color: var(--accent); }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 24px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .card-header { margin-bottom: 24px; }
        .card-title { font-size: 20px; font-weight: 500; margin-bottom: 6px; display: flex; align-items: center; gap: 10px; }
        .card-subtitle { color: var(--muted); font-size: 14px; }

        /* Settings Toggle */
        .setting-row { display: flex; align-items: center; justify-content: space-between; padding: 16px 0; border-bottom: 1px solid var(--border); }
        .setting-row:last-child { border-bottom: none; }
        .setting-info { flex: 1; }
        .setting-label { font-weight: 500; font-size: 15px; }
        .setting-desc { font-size: 13px; color: var(--muted); }

        .switch {
            position: relative; display: inline-block; width: 44px; height: 24px;
        }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
            background-color: rgba(255,255,255,0.1); transition: .4s; border-radius: 24px;
        }
        .slider:before {
            position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px;
            background-color: #fff; transition: .4s; border-radius: 50%;
        }
        input:checked + .slider { background-color: var(--accent); }
        input:checked + .slider:before { transform: translateX(20px); background-color: #1a1206; }

        /* Memory List */
        .memory-item {
            padding: 16px; background: var(--surface-light); border-radius: 14px; margin-bottom: 12px;
            display: flex; align-items: flex-start; gap: 16px; border: 1px solid transparent; transition: all 0.2s;
        }
        .memory-item:hover { border-color: var(--accent-soft); background: rgba(255,255,255,0.04); }
        .memory-meta { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
        .memory-text { font-size: 14px; color: rgba(255,255,255,0.85); flex: 1; }
        .memory-tags { display: flex; gap: 6px; margin-top: 8px; flex-wrap: wrap; }
        .tag { font-size: 10px; padding: 2px 8px; background: rgba(255,255,255,0.06); border-radius: 50px; color: var(--muted); }

        /* Sessions */
        .session-item {
            display: flex; align-items: center; justify-content: space-between; padding: 14px 0; border-bottom: 1px solid var(--border);
        }
        .session-item:last-child { border-bottom: none; }
        .session-info { font-size: 14px; }
        .session-date { font-size: 12px; color: var(--muted); }
        .badge-current { background: var(--accent-soft); color: var(--accent); font-size: 10px; padding: 2px 8px; border-radius: 50px; font-weight: 600; }

        /* Buttons */
        .btn {
            display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 12px;
            font-family: 'Outfit', sans-serif; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s; border: none;
        }
        .btn-primary { background: var(--accent); color: #1a1206; }
        .btn-primary:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn-outline { background: transparent; border: 1px solid var(--border); color: var(--muted); }
        .btn-outline:hover { border-color: var(--accent); color: var(--accent); }
        .btn-danger { background: rgba(239,68,68,0.1); color: var(--danger); border: 1px solid rgba(239,68,68,0.2); }
        .btn-danger:hover { background: var(--danger); color: #fff; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }

        /* Toast / Alerts */
        .alert { padding: 14px 20px; border-radius: 12px; margin-bottom: 24px; font-size: 14px; animation: slideIn 0.3s ease; }
        .alert-success { background: rgba(52,211,153,0.1); color: #34d399; border: 1px solid rgba(52,211,153,0.2); }
        .alert-danger { background: rgba(239,68,68,0.1); color: var(--danger); border: 1px solid rgba(239,68,68,0.2); }

        @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        .empty-state { text-align: center; padding: 40px 0; color: var(--muted); font-size: 14px; }
        .pagination { display: flex; justify-content: center; gap: 10px; margin-top: 20px; }
    </style>
</head>
<body>

<div class="container">
    <header>
        <div>
            <a href="/app.php" class="back-link">← Back to App</a>
            <h1>Privacy & Memory</h1>
        </div>
        <a href="/api/export.php" class="btn btn-outline">
            <span>📥</span> Download Wellness Diary
        </a>
    </header>

    <?php if ($msg): ?>
        <div class="alert alert-success"><?= h($msg) ?></div>
    <?php endif ?>
    <?php if ($err): ?>
        <div class="alert alert-danger"><?= h($err) ?></div>
    <?php endif ?>

    <!-- 1. Privacy Settings -->
    <section class="card">
        <div class="card-header">
            <h2 class="card-title"><span>🛡️</span> Privacy Settings</h2>
            <p class="card-subtitle">Control how Solen remembers you.</p>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="update_settings">
            
            <div class="setting-row">
                <div class="setting-info">
                    <div class="setting-label">Advanced Memory System</div>
                    <div class="setting-desc">Allow Solen to extract and store key moments from your conversations for long-term continuity.</div>
                </div>
                <label class="switch">
                    <input type="checkbox" name="memory_enabled" <?= $memoryEnabled ? 'checked' : '' ?> onchange="this.form.submit()">
                    <span class="slider"></span>
                </label>
            </div>

            <div class="setting-row">
                <div class="setting-info">
                    <div class="setting-label">Emotional Intelligence</div>
                    <div class="setting-desc">Allow the AI to detect emotional patterns and provide proactive nudges.</div>
                </div>
                <label class="switch">
                    <input type="checkbox" checked disabled>
                    <span class="slider"></span>
                </label>
            </div>
        </form>
    </section>

    <!-- 2. Memory Control Panel -->
    <section class="card">
        <div class="card-header">
            <h2 class="card-title"><span>📖</span> Memory Control Panel</h2>
            <p class="card-subtitle">View and manage the specific things Solen has learned about you.</p>
        </div>

        <?php if (empty($episodes)): ?>
            <div class="empty-state">No memories stored yet. Talk to your coach to build continuity.</div>
        <?php else: ?>
            <div class="memory-list">
                <?php foreach ($episodes as $ep): ?>
                    <div class="memory-item">
                        <div class="memory-text">
                            <div class="memory-meta"><?= h(str_replace('_', ' ', $ep['type'])) ?> · <?= h($ep['session_date']) ?></div>
                            <?= h($ep['summary']) ?>
                            <?php $tags = json_decode($ep['tags'] ?? '[]', true); if ($tags): ?>
                                <div class="memory-tags">
                                    <?php foreach ($tags as $t): ?><span class="tag"><?= h($t) ?></span><?php endforeach ?>
                                </div>
                            <?php endif ?>
                        </div>
                        <form method="POST" onsubmit="return confirm('Delete this memory? Solen will no longer remember this detail.')">
                            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                            <input type="hidden" name="action" value="delete_memory">
                            <input type="hidden" name="episode_id" value="<?= $ep['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </div>
                <?php endforeach ?>
            </div>

            <?php if ($totalEps > $perPage): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page-1 ?>" class="btn btn-outline btn-sm">Previous</a>
                    <?php endif ?>
                    <span style="font-size: 13px; color: var(--muted); align-self: center;">Page <?= $page ?> of <?= ceil($totalEps / $perPage) ?></span>
                    <?php if ($page * $perPage < $totalEps): ?>
                        <a href="?page=<?= $page+1 ?>" class="btn btn-outline btn-sm">Next</a>
                    <?php endif ?>
                </div>
            <?php endif ?>
        <?php endif ?>
    </section>

    <!-- 3. Session Management -->
    <section class="card">
        <div class="card-header" style="display: flex; align-items: flex-start; justify-content: space-between;">
            <div>
                <h2 class="card-title"><span>📱</span> Active Sessions</h2>
                <p class="card-subtitle">Manage devices where you are currently signed in.</p>
            </div>
            <?php if (count($sessions) > 1): ?>
                <form method="POST" onsubmit="return confirm('Sign out of all other devices?')">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="revoke_all">
                    <button type="submit" class="btn btn-outline btn-sm">Revoke All Others</button>
                </form>
            <?php endif ?>
        </div>

        <div class="session-list">
            <?php foreach ($sessions as $s): ?>
                <?php $isCurrent = ($s['token'] === $_SESSION['db_token']); ?>
                <div class="session-item">
                    <div class="session-info">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <strong>Browser Session</strong>
                            <?php if ($isCurrent): ?><span class="badge-current">This Device</span><?php endif ?>
                        </div>
                        <div class="session-date">Started: <?= date('M j, Y H:i', strtotime($s['created_at'])) ?></div>
                        <div class="session-date">Expires: <?= date('M j, Y', strtotime($s['expires_at'])) ?></div>
                    </div>
                    <?php if (!$isCurrent): ?>
                        <form method="POST">
                            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                            <input type="hidden" name="action" value="revoke_session">
                            <input type="hidden" name="token" value="<?= h($s['token']) ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Sign Out</button>
                        </form>
                    <?php endif ?>
                </div>
            <?php endforeach ?>
        </div>
    </section>

    <!-- 4. Billing & Subscription (Phase 8) -->
    <section class="card">
        <div class="card-header" style="display: flex; align-items: flex-start; justify-content: space-between;">
            <div>
                <h2 class="card-title"><span>💳</span> Billing & Membership</h2>
                <p class="card-subtitle">Manage your subscription and view payment history.</p>
            </div>
            <a href="/pricing.php" class="btn btn-primary btn-sm">Upgrade / Manage</a>
        </div>
        
        <?php
        $subs = db_query("SELECT * FROM subscriptions WHERE user_id=? ORDER BY started_at DESC", [$userId]);
        ?>
        <div style="background:var(--surface-light);border-radius:14px;overflow:hidden;margin-top:10px">
          <table style="width:100%;font-size:13px;border-collapse:collapse">
            <thead>
              <tr style="border-bottom:1px solid var(--border)">
                <th style="text-align:left;padding:12px 16px;color:var(--muted)">Plan</th>
                <th style="text-align:left;padding:12px 16px;color:var(--muted)">Status</th>
                <th style="text-align:left;padding:12px 16px;color:var(--muted)">Amount</th>
                <th style="text-align:left;padding:12px 16px;color:var(--muted)">Date</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($subs as $s): ?>
                <tr style="border-bottom:1px solid var(--border)">
                  <td style="padding:12px 16px"><strong>Solen <?= ucfirst($s['plan']) ?></strong></td>
                  <td style="padding:12px 16px">
                    <span style="padding:2px 8px;border-radius:4px;font-size:10px;background:<?= $s['status']==='active'?'rgba(34,197,94,0.1)':'rgba(255,255,255,0.05)' ?>;color:<?= $s['status']==='active'?'#4ade80':'var(--muted)' ?>">
                      <?= ucfirst($s['status']) ?>
                    </span>
                  </td>
                  <td style="padding:12px 16px">$<?= number_format($s['amount'], 2) ?></td>
                  <td style="padding:12px 16px;color:var(--muted)"><?= date('M j, Y', strtotime($s['started_at'])) ?></td>
                </tr>
              <?php endforeach ?>
              <?php if (!$subs): ?>
                <tr><td colspan="4" style="text-align:center;padding:24px;color:var(--muted)">No billing records found.</td></tr>
              <?php endif ?>
            </tbody>
          </table>
        </div>
    </section>

    <section class="card" style="border-color: rgba(239,68,68,0.2); background: rgba(239,68,68,0.02);">
        <div class="card-header">
            <h2 class="card-title" style="color: var(--danger);"><span>⚠️</span> Critical Safety</h2>
            <p class="card-subtitle">Solen is not a medical device. If you are in immediate danger, please contact local emergency services or a crisis line.</p>
        </div>
        <div style="font-size: 14px; opacity: 0.8;">
            <p>Crisis Resources:</p>
            <ul style="margin: 10px 20px;">
                <li>USA: Call or text <strong>988</strong></li>
                <li>UK: Call <strong>111</strong> or <strong>999</strong></li>
                <li>Global: <a href="https://findahelpline.com" target="_blank" style="color: var(--accent);">findahelpline.com</a></li>
            </ul>
        </div>
    </section>

</div>

</body>
</html>
