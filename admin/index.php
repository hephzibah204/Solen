<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/admin_layout.php';
require_admin();

$stats = [
    'users'         => db_count('users'),
    'active_subs'   => db_count('subscriptions', "status IN ('active','trial')"),
    'posts'         => db_count('blog_posts', "status='published'"),
    'moods'         => db_count('mood_logs'),
];
$mrr = db_one("SELECT COALESCE(SUM(amount),0) as total FROM subscriptions WHERE status='active' AND billing_cycle='monthly'")['total'] ?? 0;
$arr = $mrr * 12 + (db_one("SELECT COALESCE(SUM(amount),0) as total FROM subscriptions WHERE status='active' AND billing_cycle='yearly'")['total'] ?? 0);

// ── Real analytics queries ─────────────────────────────────────────────────
// Signups per day (last 30 days)
$signupsByDay = db_query(
    "SELECT date(created_at) as day, COUNT(*) as n FROM users
      WHERE created_at >= date('now','-30 days') GROUP BY day ORDER BY day ASC"
);
// MRR contribution added per week (last 12 weeks) — approximation
$mrrByWeek = db_query(
    "SELECT strftime('%Y-W%W', s.created_at) as week, COALESCE(SUM(s.amount),0) as n
       FROM subscriptions s WHERE s.status='active' AND s.created_at >= date('now','-84 days')
       GROUP BY week ORDER BY week ASC"
);
// Trial → paid conversion
$trials  = db_count('subscriptions', "status='trial'") ?: 1;
$paid    = db_count('subscriptions', "status='active'");
$convRate = round($paid / ($paid + $trials) * 100, 1);
// Churn this month (cancelled this calendar month)
$churn = db_count('subscriptions', "status='cancelled' AND strftime('%Y-%m',cancelled_at)=strftime('%Y-%m','now')");
// Avg mood (all time)
$avgMoodRow = db_one("SELECT ROUND(AVG(score),2) as avg FROM mood_logs");
$avgMood = $avgMoodRow['avg'] ?? 0;

$recentUsers = db_query("SELECT * FROM users ORDER BY created_at DESC LIMIT 8");
$recentPosts = db_query("SELECT p.*, u.name as author FROM blog_posts p LEFT JOIN users u ON p.author_id=u.id ORDER BY p.created_at DESC LIMIT 5");
$planCounts  = db_query("SELECT plan, COUNT(*) as n FROM users GROUP BY plan ORDER BY n DESC");
$moodData    = db_query("SELECT logged_date, ROUND(AVG(score),1) as avg FROM mood_logs GROUP BY logged_date ORDER BY logged_date DESC LIMIT 14");

// Helper: build a simple SVG sparkline from an array of numeric values
function sparkline(array $values, string $color = '#c5a572', int $w = 200, int $h = 40): string {
    $values = array_values($values);
    $n = count($values);
    if ($n < 2) return '';
    $min = min($values); $max = max($values);
    $range = ($max - $min) ?: 1;
    $pts = [];
    for ($i = 0; $i < $n; $i++) {
        $x = round(($i / ($n-1)) * $w, 1);
        $y = round($h - (($values[$i] - $min) / $range) * ($h * 0.8) - $h * 0.1, 1);
        $pts[] = "$x,$y";
    }
    $path = 'M ' . implode(' L ', $pts);
    $fill = $path . " L {$w},{$h} L 0,{$h} Z";
    return "<svg width='{$w}' height='{$h}' viewBox='0 0 {$w} {$h}' style='display:block'>
      <defs><linearGradient id='sg' x1='0' y1='0' x2='0' y2='1'>
        <stop offset='0%' stop-color='{$color}' stop-opacity='0.25'/>
        <stop offset='100%' stop-color='{$color}' stop-opacity='0'/>
      </linearGradient></defs>
      <path d='{$fill}' fill='url(#sg)'/>
      <path d='{$path}' fill='none' stroke='{$color}' stroke-width='2' stroke-linejoin='round' stroke-linecap='round'/>
    </svg>";
}

$signupValues = array_column($signupsByDay, 'n');
$mrrValues    = array_column($mrrByWeek,    'n');

admin_head('Dashboard');
admin_sidebar('overview');
?>
<div class="main">
  <div class="topbar">
    <div class="topbar-title">Dashboard Overview</div>
    <div class="topbar-right">
      <span class="text-muted text-sm"><?= date('D, M j Y') ?></span>
    </div>
  </div>
  <div class="content">
    <?php admin_flash() ?>

    <!-- Stat Cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">Total Users</div>
        <div class="stat-value"><?= number_format($stats['users']) ?></div>
        <div class="stat-sub">All registered accounts</div>
        <?php if ($signupValues): echo sparkline($signupValues, '#c5a572', 160, 32); endif ?>
      </div>
      <div class="stat-card">
        <div class="stat-label">Est. MRR</div>
        <div class="stat-value">$<?= number_format($mrr) ?></div>
        <div class="stat-sub">ARR: $<?= number_format($arr) ?></div>
        <?php if ($mrrValues): echo sparkline($mrrValues, '#86efac', 160, 32); endif ?>
      </div>
      <div class="stat-card">
        <div class="stat-label">Trial → Paid</div>
        <div class="stat-value"><?= $convRate ?>%</div>
        <div class="stat-sub"><?= $paid ?> paid · <?= $trials ?> still trialling</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Churn This Month</div>
        <div class="stat-value"><?= $churn ?></div>
        <div class="stat-sub">Cancellations in <?= date('F') ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Active Subscribers</div>
        <div class="stat-value"><?= number_format($stats['active_subs']) ?></div>
        <div class="stat-sub">Including free trials</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Avg Mood Score</div>
        <div class="stat-value"><?= $avgMood ?><span style="font-size:16px;opacity:0.4"> /5</span></div>
        <div class="stat-sub"><?= number_format($stats['moods']) ?> total check-ins</div>
      </div>
    </div>

    <!-- Two columns -->
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:20px">

      <!-- Recent Users -->
      <div class="card">
        <div class="card-header">
          <div class="card-title">Recent Users</div>
          <a href="/admin/users.php" class="btn btn-ghost btn-sm">View all</a>
        </div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Name</th><th>Email</th><th>Plan</th><th>Joined</th></tr></thead>
            <tbody>
            <?php foreach ($recentUsers as $u): ?>
            <tr>
              <td><strong><?= h($u['name']) ?></strong></td>
              <td class="text-muted"><?= h($u['email']) ?></td>
              <td><?= plan_badge($u['plan']) ?></td>
              <td class="text-muted text-sm"><?= time_ago($u['created_at']) ?></td>
            </tr>
            <?php endforeach ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Plan breakdown -->
      <div class="card">
        <div class="card-header"><div class="card-title">Users by Plan</div></div>
        <?php foreach ($planCounts as $p): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border)">
          <div><?= plan_badge($p['plan']) ?></div>
          <div style="font-size:20px;font-family:'Cormorant Garamond',serif;color:var(--accent)"><?= $p['n'] ?></div>
        </div>
        <?php endforeach ?>
        <div style="margin-top:16px"><a href="/admin/subscriptions.php" class="btn btn-ghost btn-sm" style="width:100%;justify-content:center">Manage subscriptions →</a></div>
      </div>
    </div>

    <!-- Recent Blog Posts -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">Recent Blog Posts</div>
        <div style="display:flex;gap:8px">
          <a href="/admin/blog.php?action=new" class="btn btn-primary btn-sm">+ New Post</a>
          <a href="/admin/blog.php" class="btn btn-ghost btn-sm">View all</a>
        </div>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Title</th><th>Category</th><th>Author</th><th>Status</th><th>Views</th><th>Date</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($recentPosts as $p): ?>
          <tr>
            <td class="truncate"><strong><?= h($p['title']) ?></strong></td>
            <td class="text-muted"><?= h($p['category']) ?></td>
            <td class="text-muted"><?= h($p['author'] ?? '—') ?></td>
            <td><?= status_badge($p['status']) ?></td>
            <td class="text-muted"><?= number_format($p['views']) ?></td>
            <td class="text-muted text-sm"><?= time_ago($p['created_at']) ?></td>
            <td><a href="/admin/blog.php?action=edit&id=<?= $p['id'] ?>" class="btn btn-ghost btn-sm">Edit</a></td>
          </tr>
          <?php endforeach ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>
</body></html>
