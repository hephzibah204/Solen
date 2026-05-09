<?php
/**
 * /timeline.php — Phase 5/6: Emotional Timeline & Growth Analytics
 *
 * Premium visual dashboard showing:
 *   - Mood trend chart (90-day sparkline)
 *   - Milestone moments
 *   - Growth score + consistency stats
 *   - Ritual streak progress
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/retention.php';
require_login();

$user      = current_user();
$userId    = (int)$user['id'];
$firstName = explode(' ', $user['name'])[0];
$site      = get_setting('site_name', 'Solen');
$coach     = db_one("SELECT * FROM coach_profiles WHERE user_id=?", [$userId]);
$coachName = $coach['coach_name'] ?? 'your coach';

// Data
$summary      = analytics_get_summary($userId);
$streaks      = ritual_get_streak($userId);
$consistency  = analytics_get_consistency($userId);
$milestones   = timeline_get_milestones($userId);
$todayStatus  = ritual_get_today_status($userId);
$nextReminder = reminder_get_next($userId);

// Trend data for chart (30 days)
$trendRows = analytics_get_mood_trend($userId, 30);
$trendLabels = array_column($trendRows, 'day');
$trendValues = array_map(fn($r) => $r['avg_mood'] !== null ? round((float)$r['avg_mood'], 2) : null, $trendRows);

$moodTrend  = $summary['mood_trend'] ?? 'unknown';
$trendColor = match($moodTrend) {
    'improving' => '#34d399',
    'declining' => '#f87171',
    default     => '#a78bfa',
};

$milestoneTypeIcons = [
    'streak'       => '🔥',
    'mood_high'    => '📈',
    'breakthrough' => '💡',
    'recovery'     => '🌿',
    'first_ritual' => '✨',
    'growth_score' => '🌟',
    'default'      => '⭐',
];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"/>
<title>Your Growth Journey — <?= h($site) ?></title>
<?php require_once __DIR__ . '/includes/pwa.php'; pwa_head(); ?>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#07070f;--surface:#0c0c1a;--surface2:#111128;
  --border:rgba(255,255,255,0.07);--border2:rgba(255,255,255,0.04);
  --accent:#b8956a;--accent2:rgba(184,149,106,0.12);
  --text:#f2ede8;--muted:rgba(242,237,232,0.42);
  --green:#34d399;--red:#f87171;--purple:#a78bfa;
}
body{background:var(--bg);color:var(--text);font-family:'Outfit',sans-serif;min-height:100vh}
a{text-decoration:none;color:inherit}

/* Nav */
nav{border-bottom:1px solid var(--border);background:rgba(7,7,15,0.95);position:sticky;top:0;z-index:50;backdrop-filter:blur(12px)}
.nav-in{max-width:1100px;margin:0 auto;padding:0 28px;display:flex;align-items:center;justify-content:space-between;height:58px}
.logo{font-family:'Playfair Display',serif;font-size:22px;color:var(--accent);display:flex;align-items:center;gap:9px}
.logo-dot{width:6px;height:6px;background:var(--accent);border-radius:50%;box-shadow:0 0 7px var(--accent)}
.nav-links{display:flex;gap:4px;align-items:center}
.nav-link{padding:7px 14px;border-radius:8px;font-size:13px;color:var(--muted);transition:color 0.2s,background 0.2s}
.nav-link:hover,.nav-link.active{color:var(--text);background:rgba(255,255,255,0.05)}

/* Layout */
.wrap{max-width:1100px;margin:0 auto;padding:36px 28px 80px}

/* Page header */
.page-header{margin-bottom:36px}
.page-header h1{font-family:'Playfair Display',serif;font-size:32px;font-weight:400;margin-bottom:6px}
.page-header p{color:var(--muted);font-size:15px}

/* Grid */
.grid-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px}
@media(max-width:800px){.grid-stats{grid-template-columns:repeat(2,1fr)}}
@media(max-width:480px){.grid-stats{grid-template-columns:1fr}}

/* Stat card */
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:22px 20px;position:relative;overflow:hidden}
.stat-card::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(255,255,255,0.02) 0%,transparent 60%);pointer-events:none}
.stat-label{font-size:11px;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:var(--muted);margin-bottom:10px}
.stat-value{font-size:36px;font-weight:600;line-height:1;margin-bottom:4px}
.stat-sub{font-size:12px;color:var(--muted)}
.stat-icon{position:absolute;top:18px;right:18px;font-size:22px;opacity:0.4}

/* Chart section */
.section{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:28px;margin-bottom:24px}
.section-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px}
.section-title{font-family:'Playfair Display',serif;font-size:20px;font-weight:400}
.section-badge{font-size:11px;padding:4px 10px;border-radius:20px;font-weight:600;letter-spacing:0.04em}
.badge-improving{background:rgba(52,211,153,0.15);color:#34d399}
.badge-declining{background:rgba(248,113,113,0.15);color:#f87171}
.badge-stable{background:rgba(167,139,250,0.15);color:#a78bfa}
.badge-unknown{background:rgba(255,255,255,0.06);color:var(--muted)}

/* Mood chart */
.chart-wrap{position:relative;height:200px}

/* Milestones */
.milestone-list{display:flex;flex-direction:column;gap:12px}
.milestone-item{display:flex;align-items:flex-start;gap:14px;padding:14px 16px;background:var(--surface2);border-radius:12px;border:1px solid var(--border2)}
.milestone-icon{font-size:22px;flex-shrink:0;margin-top:1px}
.milestone-info{}
.milestone-title{font-size:14px;font-weight:500;margin-bottom:2px}
.milestone-desc{font-size:12px;color:var(--muted)}
.milestone-date{font-size:11px;color:var(--muted);margin-top:4px}
.milestone-empty{color:var(--muted);font-size:14px;text-align:center;padding:32px 0}

/* Ritual progress */
.ritual-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
@media(max-width:600px){.ritual-grid{grid-template-columns:1fr}}
.ritual-period{background:var(--surface2);border:1px solid var(--border2);border-radius:12px;padding:16px}
.ritual-period-title{font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.07em;color:var(--muted);margin-bottom:12px}
.ritual-item{display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:1px solid var(--border2)}
.ritual-item:last-child{border-bottom:none}
.ritual-check{width:18px;height:18px;border-radius:50%;border:2px solid var(--border);flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:9px;transition:all 0.3s}
.ritual-check.done{background:var(--green);border-color:var(--green);color:#07070f}
.ritual-item-label{font-size:13px;color:var(--text)}
.ritual-item-icon{font-size:14px}

/* Progress bar */
.progress-bar-wrap{margin-top:14px}
.progress-bar-label{display:flex;justify-content:space-between;font-size:12px;color:var(--muted);margin-bottom:6px}
.progress-bar{background:rgba(255,255,255,0.07);border-radius:6px;height:6px;overflow:hidden}
.progress-bar-fill{height:100%;border-radius:6px;transition:width 0.6s ease}

/* Two-col layout */
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:20px}
@media(max-width:700px){.two-col{grid-template-columns:1fr}}

/* Streak flame */
.streak-display{text-align:center;padding:16px 0}
.streak-num{font-size:56px;font-weight:700;line-height:1;color:var(--accent)}
.streak-label{font-size:13px;color:var(--muted);margin-top:4px}

/* Trend label */
.trend-up{color:var(--green)}
.trend-down{color:var(--red)}
.trend-stable{color:var(--purple)}
</style>
</head>
<body>
<nav>
  <div class="nav-in">
    <a href="/dashboard.php" class="logo"><span class="logo-dot"></span> <?= h($site) ?></a>
    <div class="nav-links">
      <a href="/dashboard.php"     class="nav-link">Dashboard</a>
      <a href="/app.php"           class="nav-link">Chat</a>
      <a href="/timeline.php"      class="nav-link active">Growth</a>
      <a href="/settings.php"      class="nav-link">Settings</a>
    </div>
  </div>
</nav>

<div class="wrap">
  <div class="page-header">
    <h1>Your Growth Journey</h1>
    <p>A living record of <?= h($firstName) ?>'s emotional evolution with <?= h($coachName) ?>.</p>
  </div>

  <!-- Stat cards -->
  <div class="grid-stats">
    <div class="stat-card">
      <div class="stat-label">Growth Score</div>
      <div class="stat-value" style="color:var(--accent)"><?= $summary['overall_score'] ?? 0 ?></div>
      <div class="stat-sub">out of 100 this month</div>
      <div class="stat-icon">🌟</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Ritual Streak</div>
      <div class="stat-value" style="color:#fb923c"><?= $streaks['current'] ?></div>
      <div class="stat-sub">days (best: <?= $streaks['longest'] ?>)</div>
      <div class="stat-icon">🔥</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Mood Trend</div>
      <?php
        $delta = $summary['mood_delta'] ?? null;
        $deltaStr = $delta !== null ? ($delta >= 0 ? '+' . $delta : (string)$delta) : '—';
        $deltaClass = $delta === null ? '' : ($delta > 0 ? 'trend-up' : ($delta < 0 ? 'trend-down' : 'trend-stable'));
      ?>
      <div class="stat-value <?= $deltaClass ?>" style="font-size:28px"><?= $deltaStr ?></div>
      <div class="stat-sub">vs previous 30 days</div>
      <div class="stat-icon">📊</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Check-ins (30d)</div>
      <div class="stat-value" style="color:var(--purple)"><?= $consistency['checkin_days_last_30'] ?></div>
      <div class="stat-sub"><?= $consistency['checkin_consistency_pct'] ?>% consistency</div>
      <div class="stat-icon">✅</div>
    </div>
  </div>

  <!-- Mood trend chart -->
  <div class="section">
    <div class="section-head">
      <div class="section-title">Mood Over 30 Days</div>
      <span class="section-badge badge-<?= $moodTrend ?>">
        <?= match($moodTrend){ 'improving'=>'↑ Improving','declining'=>'↓ Declining','stable'=>'→ Stable',default=>'No data' } ?>
      </span>
    </div>
    <div class="chart-wrap">
      <canvas id="moodChart"></canvas>
    </div>
  </div>

  <!-- Today's Rituals + Streak -->
  <div class="two-col">
    <div class="section">
      <div class="section-head">
        <div class="section-title">Today's Rituals</div>
        <span style="font-size:13px;color:var(--muted)"><?= $todayStatus['completed'] ?>/<?= $todayStatus['total'] ?> done</span>
      </div>
      <div class="ritual-grid" style="grid-template-columns:1fr">
        <?php foreach (RITUAL_PERIODS as $period):
          $rits = $todayStatus['by_period'][$period] ?? [];
          $periodDefaults = RITUAL_DEFAULTS[$period];
          $doneKeys = array_column($rits, 'ritual_key');
          $label = ucfirst($period);
          $periodEmoji = match($period){ 'morning'=>'🌅','evening'=>'🌙','weekly'=>'📅' };
        ?>
        <div class="ritual-period">
          <div class="ritual-period-title"><?= $periodEmoji ?> <?= $label ?></div>
          <?php foreach ($periodDefaults as $r):
            $done = in_array($r['key'], $doneKeys);
          ?>
          <div class="ritual-item">
            <div class="ritual-check <?= $done ? 'done' : '' ?>"><?= $done ? '✓' : '' ?></div>
            <span class="ritual-item-icon"><?= $r['icon'] ?></span>
            <span class="ritual-item-label" style="<?= $done ? 'opacity:0.5;text-decoration:line-through' : '' ?>"><?= h($r['label']) ?></span>
          </div>
          <?php endforeach ?>
        </div>
        <?php endforeach ?>
      </div>
      <div class="progress-bar-wrap">
        <div class="progress-bar-label">
          <span>Daily progress</span>
          <span><?= $todayStatus['pct'] ?>%</span>
        </div>
        <div class="progress-bar">
          <div class="progress-bar-fill" style="width:<?= $todayStatus['pct'] ?>%;background:var(--accent)"></div>
        </div>
      </div>
      <div style="margin-top:18px;text-align:center">
        <a href="/app.php" style="font-size:13px;color:var(--accent);border:1px solid rgba(184,149,106,0.3);padding:9px 20px;border-radius:50px;display:inline-block;transition:all 0.2s" onmouseover="this.style.background='rgba(184,149,106,0.08)'" onmouseout="this.style.background='transparent'">
          Open Rituals in Chat →
        </a>
      </div>
    </div>

    <div class="section">
      <div class="section-head">
        <div class="section-title">Consistency</div>
      </div>
      <div class="streak-display">
        <div class="streak-num"><?= $streaks['current'] ?>🔥</div>
        <div class="streak-label">day ritual streak</div>
      </div>
      <div style="display:flex;flex-direction:column;gap:14px;margin-top:8px">
        <?php
          $metrics = [
            ['Check-in consistency', $consistency['checkin_consistency_pct'], 100, '#a78bfa'],
            ['Ritual completion', $todayStatus['pct'], 100, '#fb923c'],
            ['Breakthroughs this month', min($summary['breakthroughs_30d'] ?? 0, 5) * 20, 100, '#34d399'],
          ];
        ?>
        <?php foreach ($metrics as [$label, $val, $max, $color]): ?>
        <div>
          <div class="progress-bar-label">
            <span style="font-size:13px"><?= $label ?></span>
            <span><?= $val ?>%</span>
          </div>
          <div class="progress-bar">
            <div class="progress-bar-fill" style="width:<?= min((int)$val, 100) ?>%;background:<?= $color ?>"></div>
          </div>
        </div>
        <?php endforeach ?>
      </div>
      <?php if ($nextReminder): ?>
      <div style="margin-top:20px;padding:12px 14px;background:rgba(184,149,106,0.06);border:1px solid rgba(184,149,106,0.15);border-radius:10px;font-size:12px;color:var(--muted)">
        ⏰ Next reminder: <strong style="color:var(--text)"><?= ucwords(str_replace('_', ' ', $nextReminder['reminder_type'])) ?></strong>
        at <?= date('g:i A', strtotime($nextReminder['scheduled_at'])) ?>
      </div>
      <?php endif ?>
    </div>
  </div>

  <!-- Milestones -->
  <div class="section">
    <div class="section-head">
      <div class="section-title">Milestone Moments</div>
      <span style="font-size:12px;color:var(--muted)"><?= count($milestones) ?> total</span>
    </div>
    <?php if (!$milestones): ?>
    <div class="milestone-empty">Your first milestone is just around the corner. 🌱</div>
    <?php else: ?>
    <div class="milestone-list">
      <?php foreach (array_slice($milestones, 0, 12) as $m):
        $icon = $milestoneTypeIcons[$m['type']] ?? $milestoneTypeIcons['default'];
      ?>
      <div class="milestone-item">
        <div class="milestone-icon"><?= $icon ?></div>
        <div class="milestone-info">
          <div class="milestone-title"><?= h($m['title']) ?></div>
          <?php if ($m['description']): ?><div class="milestone-desc"><?= h($m['description']) ?></div><?php endif ?>
          <div class="milestone-date"><?= date('M j, Y', strtotime($m['date'])) ?></div>
        </div>
      </div>
      <?php endforeach ?>
    </div>
    <?php endif ?>
  </div>
</div>

<script>
// Mood chart
const labels  = <?= json_encode($trendLabels) ?>;
const values  = <?= json_encode($trendValues) ?>;
const trendColor = <?= json_encode($trendColor) ?>;

const ctx = document.getElementById('moodChart').getContext('2d');

const gradient = ctx.createLinearGradient(0, 0, 0, 200);
gradient.addColorStop(0, trendColor + '33');
gradient.addColorStop(1, trendColor + '00');

new Chart(ctx, {
  type: 'line',
  data: {
    labels: labels.map(d => {
      const dt = new Date(d);
      return dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }),
    datasets: [{
      data: values,
      borderColor: trendColor,
      backgroundColor: gradient,
      borderWidth: 2.5,
      pointRadius: 3,
      pointBackgroundColor: trendColor,
      pointBorderColor: '#07070f',
      pointBorderWidth: 2,
      tension: 0.4,
      fill: true,
      spanGaps: true,
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false }, tooltip: {
      backgroundColor: '#0c0c1a',
      borderColor: 'rgba(255,255,255,0.1)',
      borderWidth: 1,
      titleColor: 'rgba(242,237,232,0.6)',
      bodyColor: '#f2ede8',
      callbacks: {
        label: ctx => ctx.parsed.y !== null ? `Mood: ${ctx.parsed.y.toFixed(1)}/10` : 'No data',
      }
    }},
    scales: {
      x: {
        grid: { color: 'rgba(255,255,255,0.04)' },
        ticks: { color: 'rgba(242,237,232,0.35)', font: { size: 11 }, maxRotation: 0, maxTicksLimit: 8 },
        border: { color: 'transparent' },
      },
      y: {
        min: 1, max: 10,
        grid: { color: 'rgba(255,255,255,0.04)' },
        ticks: { color: 'rgba(242,237,232,0.35)', font: { size: 11 }, stepSize: 2 },
        border: { color: 'transparent' },
      }
    }
  }
});
</script>
<?php pwa_body(); ?>
</body>
</html>
