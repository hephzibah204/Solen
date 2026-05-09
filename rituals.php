<?php
/**
 * /rituals.php — Phase 5/6: Daily Ritual System
 *
 * Interactive ritual completion UI with:
 *  - Morning / Evening / Weekly tabs
 *  - One-tap completion with optional note
 *  - Streak display
 *  - Mood capture per ritual
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

$hour    = (int)date('H');
$defaultPeriod = $hour < 12 ? 'morning' : ($hour < 20 ? 'evening' : 'evening');

$streaks    = ritual_get_streak($userId);
$todayStatus= ritual_get_today_status($userId);

// All rituals by period with completion state
$allRituals = [];
foreach (RITUAL_PERIODS as $p) {
    $allRituals[$p] = ritual_get_for_user($userId, $p);
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"/>
<title>Daily Rituals — <?= h($site) ?></title>
<?php require_once __DIR__ . '/includes/pwa.php'; pwa_head(); ?>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#07070f;--surface:#0c0c1a;--surface2:#111128;
  --border:rgba(255,255,255,0.07);--border2:rgba(255,255,255,0.04);
  --accent:#b8956a;--accent2:rgba(184,149,106,0.12);
  --text:#f2ede8;--muted:rgba(242,237,232,0.42);
  --green:#34d399;--purple:#a78bfa;
}
body{background:var(--bg);color:var(--text);font-family:'Outfit',sans-serif;min-height:100vh}
a{text-decoration:none;color:inherit}

nav{border-bottom:1px solid var(--border);background:rgba(7,7,15,0.95);position:sticky;top:0;z-index:50;backdrop-filter:blur(12px)}
.nav-in{max-width:860px;margin:0 auto;padding:0 24px;display:flex;align-items:center;justify-content:space-between;height:56px}
.logo{font-family:'Playfair Display',serif;font-size:21px;color:var(--accent);display:flex;align-items:center;gap:8px}
.logo-dot{width:6px;height:6px;background:var(--accent);border-radius:50%;box-shadow:0 0 6px var(--accent)}
.nav-links{display:flex;gap:4px}
.nav-link{padding:6px 13px;border-radius:8px;font-size:13px;color:var(--muted);transition:color 0.2s,background 0.2s}
.nav-link:hover,.nav-link.active{color:var(--text);background:rgba(255,255,255,0.05)}

.wrap{max-width:860px;margin:0 auto;padding:32px 24px 80px}

/* Header */
.page-head{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:28px;gap:16px;flex-wrap:wrap}
.page-head-left h1{font-family:'Playfair Display',serif;font-size:28px;font-weight:400;margin-bottom:4px}
.page-head-left p{color:var(--muted);font-size:14px}
.streak-badge{display:flex;align-items:center;gap:8px;background:rgba(251,146,60,0.1);border:1px solid rgba(251,146,60,0.2);padding:10px 16px;border-radius:12px}
.streak-badge .num{font-size:24px;font-weight:700;color:#fb923c;line-height:1}
.streak-badge .lbl{font-size:11px;color:var(--muted);line-height:1.4}

/* Tabs */
.tabs{display:flex;gap:0;background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:4px;margin-bottom:28px;width:fit-content}
.tab{padding:8px 20px;border-radius:9px;font-size:13px;font-weight:500;cursor:pointer;border:none;background:none;color:var(--muted);transition:all 0.2s;font-family:'Outfit',sans-serif}
.tab.active{background:rgba(255,255,255,0.08);color:var(--text)}
.tab:hover:not(.active){color:var(--text)}

/* Progress summary */
.day-progress{display:flex;align-items:center;gap:14px;margin-bottom:28px;padding:16px 20px;background:var(--surface);border:1px solid var(--border);border-radius:14px}
.day-progress-pct{font-size:28px;font-weight:700;color:var(--accent);flex-shrink:0}
.day-progress-bar{flex:1}
.day-progress-label{font-size:12px;color:var(--muted);margin-bottom:6px}
.pbar{background:rgba(255,255,255,0.07);border-radius:6px;height:7px;overflow:hidden}
.pbar-fill{height:100%;border-radius:6px;background:var(--accent);transition:width 0.5s ease}

/* Ritual cards */
.ritual-list{display:flex;flex-direction:column;gap:14px}
.ritual-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;overflow:hidden;transition:all 0.3s}
.ritual-card.done{border-color:rgba(52,211,153,0.2);background:rgba(52,211,153,0.04)}
.ritual-card-top{display:flex;align-items:center;gap:16px;padding:18px 20px;cursor:pointer}
.ritual-card-icon{font-size:26px;flex-shrink:0}
.ritual-card-info{flex:1}
.ritual-card-label{font-size:15px;font-weight:500;margin-bottom:2px;transition:opacity 0.3s}
.ritual-card.done .ritual-card-label{opacity:0.5;text-decoration:line-through}
.ritual-card-desc{font-size:13px;color:var(--muted)}
.ritual-card-duration{font-size:11px;color:var(--muted);margin-top:3px}
.ritual-card-status{flex-shrink:0}

/* Checkmark button */
.check-btn{width:34px;height:34px;border-radius:50%;border:2px solid var(--border);background:transparent;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all 0.3s;font-size:15px;color:transparent}
.check-btn.done{background:var(--green);border-color:var(--green);color:#07070f}
.check-btn:hover:not(.done){border-color:var(--green);background:rgba(52,211,153,0.1)}

/* Expand panel */
.ritual-expand{max-height:0;overflow:hidden;transition:max-height 0.4s ease,padding 0.3s}
.ritual-expand.open{max-height:400px}
.ritual-expand-inner{padding:0 20px 20px}
.ritual-prompt{font-size:15px;font-style:italic;color:var(--accent);margin-bottom:14px;font-family:'Playfair Display',serif}

/* Mood row */
.mood-row{display:flex;gap:8px;margin-bottom:14px}
.mood-btn{flex:1;border:1px solid var(--border);background:transparent;border-radius:10px;padding:8px 4px;cursor:pointer;font-family:'Outfit',sans-serif;transition:all 0.2s;display:flex;flex-direction:column;align-items:center;gap:3px}
.mood-btn:hover{border-color:var(--accent);background:rgba(184,149,106,0.06)}
.mood-btn.selected{background:rgba(184,149,106,0.12);border-color:var(--accent)}
.mood-emoji{font-size:18px}
.mood-label{font-size:10px;color:var(--muted)}

textarea.note-field{width:100%;background:rgba(255,255,255,0.04);border:1px solid var(--border);border-radius:10px;color:var(--text);font-family:'Outfit',sans-serif;font-size:14px;padding:12px 14px;resize:vertical;min-height:80px;outline:none;transition:border-color 0.2s}
textarea.note-field:focus{border-color:rgba(184,149,106,0.4)}
textarea.note-field::placeholder{color:var(--muted)}

.complete-btn{width:100%;margin-top:12px;padding:11px;border-radius:10px;border:none;background:var(--accent);color:#1a1008;font-family:'Outfit',sans-serif;font-weight:600;font-size:14px;cursor:pointer;transition:all 0.2s}
.complete-btn:hover{background:#d4ae82}
.complete-btn:disabled{opacity:0.5;cursor:not-allowed}

/* Done overlay */
.done-msg{padding:14px 16px;background:rgba(52,211,153,0.08);border:1px solid rgba(52,211,153,0.2);border-radius:10px;font-size:14px;color:var(--green);display:flex;align-items:center;gap:8px}

/* Empty state for weekly on non-sunday */
.period-note{padding:20px;text-align:center;color:var(--muted);font-size:14px}

/* Toast */
.toast{position:fixed;bottom:28px;left:50%;transform:translateX(-50%) translateY(120px);background:#1a1a2e;border:1px solid rgba(255,255,255,0.1);border-radius:12px;padding:12px 22px;font-size:14px;color:var(--text);box-shadow:0 8px 30px rgba(0,0,0,0.5);transition:transform 0.35s cubic-bezier(.175,.885,.32,1.275);z-index:999}
.toast.show{transform:translateX(-50%) translateY(0)}
</style>
</head>
<body>
<nav>
  <div class="nav-in">
    <a href="/dashboard.php" class="logo"><span class="logo-dot"></span><?= h($site) ?></a>
    <div class="nav-links">
      <a href="/dashboard.php" class="nav-link">Dashboard</a>
      <a href="/app.php"       class="nav-link">Chat</a>
      <a href="/timeline.php"  class="nav-link">Growth</a>
      <a href="/rituals.php"   class="nav-link active">Rituals</a>
    </div>
  </div>
</nav>

<div class="wrap">
  <div class="page-head">
    <div class="page-head-left">
      <h1>Daily Rituals</h1>
      <p>Small moments of intentionality that compound over time.</p>
    </div>
    <div class="streak-badge">
      <div>
        <div class="num"><?= $streaks['current'] ?>🔥</div>
        <div class="lbl">day streak<br>best: <?= $streaks['longest'] ?></div>
      </div>
    </div>
  </div>

  <!-- Tabs -->
  <div class="tabs" id="periodTabs">
    <?php foreach (['morning'=>'🌅 Morning','evening'=>'🌙 Evening','weekly'=>'📅 Weekly'] as $p => $label): ?>
    <button class="tab <?= $p === $defaultPeriod ? 'active' : '' ?>" onclick="switchPeriod('<?= $p ?>')"><?= $label ?></button>
    <?php endforeach ?>
  </div>

  <!-- Progress -->
  <div class="day-progress">
    <div class="day-progress-pct" id="todayPct"><?= $todayStatus['pct'] ?>%</div>
    <div class="day-progress-bar">
      <div class="day-progress-label">Today's completion — <?= $todayStatus['completed'] ?>/<?= $todayStatus['total'] ?> rituals</div>
      <div class="pbar"><div class="pbar-fill" id="todayBar" style="width:<?= $todayStatus['pct'] ?>%"></div></div>
    </div>
  </div>

  <!-- Ritual lists per period -->
  <?php foreach ($allRituals as $period => $rituals): ?>
  <div class="ritual-list" id="period-<?= $period ?>" style="display:<?= $period === $defaultPeriod ? 'flex' : 'none' ?>">
    <?php if ($period === 'weekly' && date('N') !== '7'): ?>
    <div class="period-note">Weekly rituals are designed for <strong>Sunday</strong> — your weekly reset.<br>You can complete them any time this week if you prefer.</div>
    <?php endif ?>
    <?php foreach ($rituals as $r): ?>
    <?php $cardId = 'card-' . $r['key']; $expandId = 'exp-' . $r['key']; ?>
    <div class="ritual-card <?= $r['completed'] ? 'done' : '' ?>" id="<?= $cardId ?>">
      <div class="ritual-card-top" onclick="toggleExpand('<?= $r['key'] ?>')">
        <div class="ritual-card-icon"><?= $r['icon'] ?></div>
        <div class="ritual-card-info">
          <div class="ritual-card-label"><?= h($r['label']) ?></div>
          <div class="ritual-card-desc"><?= h($r['description']) ?></div>
          <div class="ritual-card-duration">~<?= $r['duration_min'] ?> min</div>
        </div>
        <div class="ritual-card-status">
          <div class="check-btn <?= $r['completed'] ? 'done' : '' ?>" id="chk-<?= $r['key'] ?>"><?= $r['completed'] ? '✓' : '' ?></div>
        </div>
      </div>
      <div class="ritual-expand" id="<?= $expandId ?>">
        <div class="ritual-expand-inner">
          <?php if ($r['completed']): ?>
          <div class="done-msg">✓ Completed today — well done.</div>
          <?php else: ?>
          <div class="ritual-prompt"><?= h($r['description']) ?></div>
          <!-- Mood picker -->
          <div class="mood-row" id="mood-row-<?= $r['key'] ?>">
            <?php foreach ([['😔','Low',1],['😕','Rough',2],['😐','Okay',3],['🙂','Good',4],['😊','Great',5]] as [$emoji,$lbl,$score]): ?>
            <button class="mood-btn" data-score="<?= $score ?>" onclick="selectMood('<?= $r['key'] ?>',<?= $score ?>,this)">
              <span class="mood-emoji"><?= $emoji ?></span>
              <span class="mood-label"><?= $lbl ?></span>
            </button>
            <?php endforeach ?>
          </div>
          <textarea class="note-field" id="note-<?= $r['key'] ?>" placeholder="Optional: share a thought or reflection…"></textarea>
          <button class="complete-btn" onclick="completeRitual('<?= $r['key'] ?>')">Mark Complete ✓</button>
          <?php endif ?>
        </div>
      </div>
    </div>
    <?php endforeach ?>
  </div>
  <?php endforeach ?>
</div>

<div class="toast" id="toast"></div>

<script>
const RITUALS = <?= json_encode($allRituals) ?>;
let moodSelections = {};
let expanded = null;

function switchPeriod(p) {
  document.querySelectorAll('.ritual-list').forEach(el => el.style.display = 'none');
  document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
  document.getElementById('period-' + p).style.display = 'flex';
  event.target.classList.add('active');
}

function toggleExpand(key) {
  const exp = document.getElementById('exp-' + key);
  const isOpen = exp.classList.contains('open');
  if (expanded && expanded !== key) {
    document.getElementById('exp-' + expanded)?.classList.remove('open');
  }
  exp.classList.toggle('open', !isOpen);
  expanded = isOpen ? null : key;
}

function selectMood(key, score, btn) {
  moodSelections[key] = score;
  const row = document.getElementById('mood-row-' + key);
  row.querySelectorAll('.mood-btn').forEach(b => b.classList.remove('selected'));
  btn.classList.add('selected');
}

async function completeRitual(key) {
  const note      = document.getElementById('note-' + key)?.value || '';
  const moodScore = moodSelections[key] || null;

  const btn = document.querySelector(`#card-${key} .complete-btn`);
  if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }

  try {
    const body = { ritual_key: key, note };
    if (moodScore) body.mood_score = moodScore;

    const res  = await fetch('/api/retention.php?action=complete_ritual', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    const data = await res.json();

    if (data.ok) {
      // Update card UI
      const card = document.getElementById('card-' + key);
      card.classList.add('done');
      const chk = document.getElementById('chk-' + key);
      chk.classList.add('done'); chk.textContent = '✓';

      // Update label strikethrough
      card.querySelector('.ritual-card-label').style.opacity = '0.5';
      card.querySelector('.ritual-card-label').style.textDecoration = 'line-through';

      // Collapse panel and show done msg
      const exp = document.getElementById('exp-' + key);
      exp.querySelector('.ritual-expand-inner').innerHTML =
        '<div class="done-msg">✓ Completed today — well done.</div>';

      // Update progress
      if (data.status) {
        document.getElementById('todayPct').textContent = data.status.pct + '%';
        document.getElementById('todayBar').style.width = data.status.pct + '%';
      }

      // Streak toast
      const streak = data.streak?.current;
      showToast(streak > 1 ? `🔥 ${streak}-day streak! Keep going.` : '✅ Ritual complete — great start!');
    } else {
      showToast('Something went wrong. Please try again.');
      if (btn) { btn.disabled = false; btn.textContent = 'Mark Complete ✓'; }
    }
  } catch (e) {
    showToast('Connection error. Please try again.');
    if (btn) { btn.disabled = false; btn.textContent = 'Mark Complete ✓'; }
  }
}

function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 3400);
}
</script>
<?php pwa_body(); ?>
</body>
</html>
