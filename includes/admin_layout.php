<?php
// includes/admin_layout.php - Shared admin layout
function admin_head(string $title): void { ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title><?= h($title) ?> — Solen Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Cormorant+Garamond:ital,wght@0,400;1,400&display=swap" rel="stylesheet"/>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0a0a12;--sidebar:#0e0e1c;--card:#131325;--border:rgba(255,255,255,0.07);
  --accent:#c5a572;--gold:#e8c97a;--text:#f0ede8;--muted:rgba(240,237,232,0.45);
  --faint:rgba(240,237,232,0.08);--success:#22c55e;--danger:#ef4444;--info:#60a5fa;--warn:#f59e0b;
  --sans:'DM Sans',sans-serif;--serif:'Cormorant Garamond',serif;
}
body{background:var(--bg);color:var(--text);font-family:var(--sans);font-size:14px;line-height:1.6;display:flex;min-height:100vh}
a{color:inherit;text-decoration:none}
/* SIDEBAR */
.sidebar{width:240px;background:var(--sidebar);border-right:1px solid var(--border);display:flex;flex-direction:column;flex-shrink:0;position:sticky;top:0;height:100vh;overflow-y:auto}
.sidebar-logo{padding:24px 20px;border-bottom:1px solid var(--border);font-family:var(--serif);font-size:22px;color:var(--accent);letter-spacing:0.04em}
.sidebar-logo span{color:var(--text)}
.sidebar-logo small{display:block;font-family:var(--sans);font-size:10px;letter-spacing:0.12em;text-transform:uppercase;color:var(--muted);margin-top:2px}
.nav-section{padding:16px 12px 6px;font-size:10px;letter-spacing:0.12em;text-transform:uppercase;color:var(--muted)}
.nav-link{display:flex;align-items:center;gap:10px;padding:9px 14px;border-radius:8px;margin:2px 8px;font-size:13px;color:var(--muted);transition:all 0.15s;cursor:pointer}
.nav-link:hover{background:var(--faint);color:var(--text)}
.nav-link.active{background:rgba(197,165,114,0.12);color:var(--accent)}
.nav-link .icon{font-size:16px;width:20px;text-align:center}
.sidebar-footer{margin-top:auto;padding:16px;border-top:1px solid var(--border);font-size:12px;color:var(--muted)}
/* MAIN */
.main{flex:1;display:flex;flex-direction:column;min-width:0;overflow:auto}
.topbar{padding:16px 28px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:var(--bg);position:sticky;top:0;z-index:10}
.topbar-title{font-size:18px;font-weight:500}
.topbar-right{display:flex;align-items:center;gap:16px}
.content{padding:28px;flex:1}
/* CARDS */
.card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:22px}
.card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;padding-bottom:14px;border-bottom:1px solid var(--border)}
.card-title{font-size:15px;font-weight:500}
/* STAT CARDS */
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
.stat-card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:20px;transition:border-color 0.2s}
.stat-card:hover{border-color:rgba(197,165,114,0.3)}
.stat-label{font-size:11px;letter-spacing:0.08em;text-transform:uppercase;color:var(--muted);margin-bottom:8px}
.stat-value{font-family:var(--serif);font-size:36px;font-weight:400;color:var(--accent);line-height:1}
.stat-sub{font-size:12px;color:var(--muted);margin-top:5px}
/* TABLE */
.table-wrap{overflow-x:auto;border-radius:10px;border:1px solid var(--border)}
table{width:100%;border-collapse:collapse;font-size:13px}
th{padding:11px 14px;text-align:left;font-size:11px;letter-spacing:0.06em;text-transform:uppercase;color:var(--muted);border-bottom:1px solid var(--border);background:var(--sidebar);font-weight:500}
td{padding:11px 14px;border-bottom:1px solid var(--faint);vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:var(--faint)}
/* FORMS */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.form-grid.one{grid-template-columns:1fr}
.form-group{display:flex;flex-direction:column;gap:6px}
.form-group label{font-size:12px;letter-spacing:0.05em;text-transform:uppercase;color:var(--muted)}
.form-control{background:rgba(255,255,255,0.05);border:1px solid var(--border);border-radius:8px;padding:10px 13px;color:var(--text);font-family:var(--sans);font-size:13px;transition:border-color 0.2s;width:100%}
.form-control:focus{outline:none;border-color:var(--accent)}
textarea.form-control{resize:vertical;min-height:120px}
select.form-control{cursor:pointer}
/* BUTTONS */
.btn{display:inline-flex;align-items:center;gap:7px;padding:8px 18px;border-radius:8px;font-family:var(--sans);font-size:13px;font-weight:500;cursor:pointer;border:none;transition:all 0.2s;text-decoration:none;white-space:nowrap}
.btn-primary{background:var(--accent);color:#1a1008}
.btn-primary:hover{background:var(--gold)}
.btn-danger{background:rgba(239,68,68,0.15);color:#ef4444;border:1px solid rgba(239,68,68,0.3)}
.btn-danger:hover{background:rgba(239,68,68,0.25)}
.btn-ghost{background:transparent;color:var(--muted);border:1px solid var(--border)}
.btn-ghost:hover{color:var(--text);border-color:rgba(255,255,255,0.2)}
.btn-sm{padding:5px 12px;font-size:12px}
/* SEARCH */
.search-bar{background:rgba(255,255,255,0.05);border:1px solid var(--border);border-radius:8px;padding:8px 14px;color:var(--text);font-family:var(--sans);font-size:13px;width:240px}
.search-bar:focus{outline:none;border-color:var(--accent)}
/* ALERTS */
.alert{padding:11px 16px;border-radius:8px;margin-bottom:18px;font-size:13px;border:1px solid}
.alert-success{background:rgba(34,197,94,0.1);border-color:rgba(34,197,94,0.3);color:#86efac}
.alert-error{background:rgba(239,68,68,0.1);border-color:rgba(239,68,68,0.3);color:#fca5a5}
.alert-info{background:rgba(96,165,250,0.1);border-color:rgba(96,165,250,0.3);color:#93c5fd}
/* PAGINATION */
.pagination{display:flex;gap:6px;align-items:center;justify-content:flex-end;margin-top:16px}
.pagination a,.pagination span{padding:5px 12px;border-radius:6px;font-size:12px;border:1px solid var(--border);color:var(--muted)}
.pagination a:hover{border-color:var(--accent);color:var(--accent)}
.pagination .active{background:var(--accent);color:#1a1008;border-color:var(--accent)}
/* UTILS */
.flex{display:flex}.items-center{align-items:center}.justify-between{justify-content:space-between}.gap-2{gap:8px}.gap-3{gap:12px}.mt-1{margin-top:4px}.mt-4{margin-top:16px}.mb-4{margin-bottom:16px}.text-muted{color:var(--muted)}.text-sm{font-size:12px}.text-right{text-align:right}.truncate{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px}
</style>
</head>
<body>
<?php }

function admin_sidebar(string $active): void {
    $links = [
        'overview'      => ['icon' => '◈', 'label' => 'Dashboard',     'href' => '/admin/index.php'],
        'users'         => ['icon' => '👤', 'label' => 'Users',          'href' => '/admin/users.php'],
        'subscriptions' => ['icon' => '💳', 'label' => 'Subscriptions',  'href' => '/admin/subscriptions.php'],
        'blog'          => ['icon' => '✍', 'label' => 'Blog Posts',     'href' => '/admin/blog.php'],
        'settings'      => ['icon' => '⚙', 'label' => 'Settings',       'href' => '/admin/settings.php'],
    ];
    ?>
    <nav class="sidebar">
      <div class="sidebar-logo">Sol<span>en</span><small>Admin Panel</small></div>
      <div class="nav-section">Main</div>
      <?php foreach ($links as $key => $l): ?>
        <a class="nav-link <?= $active===$key?'active':'' ?>" href="<?= $l['href'] ?>">
          <span class="icon"><?= $l['icon'] ?></span><?= $l['label'] ?>
        </a>
      <?php endforeach; ?>
      <div class="nav-section">App</div>
      <a class="nav-link" href="/" target="_blank"><span class="icon">🌐</span> View Site</a>
      <a class="nav-link" href="/app.php" target="_blank"><span class="icon">🎙</span> Open App</a>
      <div class="sidebar-footer">
        Logged in as <strong><?= h($_SESSION['user_name'] ?? 'Admin') ?></strong><br>
        <a href="/logout.php" style="color:var(--accent);margin-top:6px;display:inline-block">Sign out →</a>
      </div>
    </nav>
<?php }

function admin_flash(): void {
    $f = get_flash();
    if (!$f) return;
    $map = ['success'=>'success','error'=>'error','info'=>'info','warning'=>'info'];
    $cls = $map[$f['type']] ?? 'info';
    echo "<div class='alert alert-$cls'>{$f['msg']}</div>";
}

function admin_foot(): void { echo '</div></div></body></html>'; }
