<?php
/**
 * /insights.php — Solen Wellness Insights & Snapshots (Phase 8)
 *
 * Generates a shareable summary of the user's progress.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

require_active_subscription();
$user   = current_user();
$userId = (int)$user['id'];

// ── DATA GATHERING ────────────────────────────────────────────────────────
$moods = db_query(
    "SELECT score, logged_date FROM mood_logs WHERE user_id=? ORDER BY logged_date DESC LIMIT 7",
    [$userId]
);
$avgMood = count($moods) ? array_sum(array_column($moods, 'score')) / count($moods) : null;

$epCount = db_count('memory_episodes', 'user_id=?', [$userId]);
$streak  = db_one("SELECT day_streak FROM coach_profiles WHERE user_id=?", [$userId])['day_streak'] ?? 0;

$topInsights = db_query(
    "SELECT summary, type, session_date FROM memory_episodes WHERE user_id=? 
     ORDER BY importance DESC, created_at DESC LIMIT 10",
    [$userId]
);

$topTags = db_query(
    "SELECT tags FROM memory_episodes WHERE user_id=? AND tags IS NOT NULL LIMIT 50",
    [$userId]
);
$allTags = [];
foreach ($topTags as $row) {
    $tags = json_decode($row['tags'], true);
    if ($tags) $allTags = array_merge($allTags, $tags);
}
$tagCounts = array_count_values($allTags);
arsort($tagCounts);
$frequentThemes = array_slice(array_keys($tagCounts), 0, 3);

$site = get_setting('site_name', 'Solen');

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wellness Insights — Solen</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #07070f;
            --surface: #0d0d1e;
            --surface-light: #16162d;
            --border: rgba(255,255,255,0.08);
            --accent: #b8956a;
            --accent-glow: rgba(184,149,106,0.3);
            --text: #f2ede8;
            --muted: rgba(242,237,232,0.45);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Outfit', sans-serif;
            padding: 40px 20px;
            line-height: 1.6;
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(184, 149, 106, 0.05) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(167, 139, 250, 0.05) 0%, transparent 40%);
            background-attachment: fixed;
        }

        .container { max-width: 900px; margin: 0 auto; }

        .layout {
            display: grid;
            grid-template-columns: 400px 1fr;
            gap: 40px;
            align-items: start;
        }

        /* ── GROWTH CARD (The Viral Card) ── */
        .snapshot-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 32px;
            padding: 48px 40px;
            position: sticky;
            top: 40px;
            overflow: hidden;
            box-shadow: 0 30px 60px rgba(0,0,0,0.5);
            text-align: center;
            border: 1px solid var(--accent-glow);
        }

        .snapshot-card::after {
            content: '';
            position: absolute;
            bottom: -100px;
            right: -100px;
            width: 250px;
            height: 250px;
            background: radial-gradient(circle, var(--accent-glow) 0%, transparent 70%);
            filter: blur(40px);
            opacity: 0.4;
        }

        .logo { font-family: 'Playfair Display', serif; font-size: 28px; color: var(--accent); margin-bottom: 40px; letter-spacing: 0.05em; }
        .label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.2em; color: var(--muted); margin-bottom: 12px; }
        .title { font-family: 'Playfair Display', serif; font-size: 36px; font-weight: 400; margin-bottom: 40px; line-height: 1.1; }
        .title em { font-style: italic; color: var(--accent); }

        .stat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-bottom: 48px; position: relative; z-index: 1; }
        .stat-item .val { font-family: 'Playfair Display', serif; font-size: 42px; color: var(--accent); line-height: 1; }
        .stat-item .lbl { font-size: 11px; color: var(--muted); margin-top: 8px; text-transform: uppercase; letter-spacing: 0.05em; }

        .themes { display: flex; flex-wrap: wrap; justify-content: center; gap: 8px; margin-bottom: 40px; }
        .theme-tag { background: var(--surface-light); padding: 6px 14px; border-radius: 50px; font-size: 12px; border: 1px solid var(--border); color: var(--text); }

        .btn-share { background: var(--accent); color: #1a1206; border: none; padding: 14px 28px; border-radius: 50px; font-weight: 600; cursor: pointer; font-family: 'Outfit', sans-serif; width: 100%; transition: all 0.2s; font-size: 15px; }
        .btn-share:hover { opacity: 0.9; transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0,0,0,0.2); }

        /* ── INSIGHTS LIST ── */
        .insights-section h2 { font-family: 'Playfair Display', serif; font-size: 28px; font-weight: 400; margin-bottom: 24px; }
        .insight-item { background: var(--surface); border: 1px solid var(--border); border-radius: 20px; padding: 24px; margin-bottom: 16px; border-left: 3px solid var(--accent); transition: transform 0.2s; }
        .insight-item:hover { transform: translateX(5px); border-color: var(--accent-glow); }
        .insight-date { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 8px; }
        .insight-text { font-size: 15px; color: rgba(255,255,255,0.85); line-height: 1.6; }
        .insight-type { display: inline-block; font-size: 10px; background: var(--surface-light); padding: 2px 8px; border-radius: 4px; color: var(--accent); margin-top: 12px; }

        .empty-state { text-align: center; padding: 60px 0; color: var(--muted); border: 1px dashed var(--border); border-radius: 20px; }

        @media (max-width: 850px) {
            .layout { grid-template-columns: 1fr; }
            .snapshot-card { position: static; margin-bottom: 40px; }
        }
    </style>
</head>
<body>

<div class="container">
    <div style="margin-bottom: 32px;">
        <a href="/app.php" style="color: var(--muted); text-decoration: none; font-size: 14px;">← Back to Coach</a>
    </div>

    <div class="layout">
        <!-- THE GROWTH CARD -->
        <aside class="snapshot-card" id="capture">
            <div class="logo">Solen</div>
            <div class="label">Evolution Report</div>
            <h1 class="title">I'm building <em>resilience</em></h1>

            <div class="stat-grid">
                <div class="stat-item">
                    <div class="val"><?= $streak ?></div>
                    <div class="lbl">Streak</div>
                </div>
                <div class="stat-item">
                    <div class="val"><?= $avgMood ? number_format($avgMood, 1) : '—' ?></div>
                    <div class="lbl">Avg Mood</div>
                </div>
                <div class="stat-item">
                    <div class="val"><?= $epCount ?></div>
                    <div class="lbl">Insights</div>
                </div>
                <div class="stat-item">
                    <div class="val"><?= count($moods) ?></div>
                    <div class="lbl">Days</div>
                </div>
            </div>

            <?php if ($frequentThemes): ?>
                <div class="themes">
                    <?php foreach ($frequentThemes as $theme): ?>
                        <span class="theme-tag">#<?= h($theme) ?></span>
                    <?php endforeach ?>
                </div>
            <?php endif ?>

            <div style="display:flex;gap:10px;">
                <button class="btn-share" onclick="shareSnapshot()" style="flex:1">Share Text</button>
                <button class="btn-share" onclick="downloadCard()" style="background:transparent;border:1px solid var(--accent);color:var(--accent);flex:1">Save Card</button>
            </div>
            <div style="font-size: 11px; color: var(--muted); margin-top: 16px;">Reflect · Recover · Rise</div>
        </aside>

        <!-- THE INSIGHTS LIST -->
        <main class="insights-section">
            <h2>Your Journey Highlights</h2>
            
            <?php if (empty($topInsights)): ?>
                <div class="empty-state">
                    <p>No major insights extracted yet.</p>
                    <p style="font-size: 13px;">Keep talking to your coach to build your growth story.</p>
                </div>
            <?php else: ?>
                <?php foreach ($topInsights as $insight): ?>
                    <div class="insight-item">
                        <div class="insight-date"><?= date('F j, Y', strtotime($insight['session_date'])) ?></div>
                        <div class="insight-text"><?= h($insight['summary']) ?></div>
                        <div class="insight-type"><?= h(str_replace('_', ' ', $insight['type'])) ?></div>
                    </div>
                <?php endforeach ?>
            <?php endif ?>

            <div style="margin-top: 40px; text-align: center;">
                <a href="/api/export.php" class="btn-share" style="background: transparent; border: 1px solid var(--border); color: var(--muted); text-decoration: none; display: inline-block; width: auto;">📥 Download Wellness Diary (JSON)</a>
            </div>
        </main>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
async function shareSnapshot() {
    const text = "I'm working on my wellness with Solen. Currently on a <?= $streak ?>-day streak! Check it out: <?= SITE_URL ?>";
    if (navigator.share) {
        try {
            await navigator.share({
                title: 'My Solen Evolution',
                text: text,
                url: '<?= SITE_URL ?>'
            });
        } catch (err) {
            copyToClipboard(text);
        }
    } else {
        copyToClipboard(text);
    }
}

async function downloadCard() {
    const btn = event.currentTarget;
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Generating...';

    const card = document.getElementById('capture');
    
    // Hide buttons for capture
    const buttons = card.querySelector('div[style*="display:flex"]');
    if (buttons) buttons.style.display = 'none';

    try {
        const canvas = await html2canvas(card, {
            backgroundColor: '#07070f',
            scale: 2, // Higher quality
            useCORS: true,
            borderRadius: 32
        });
        
        const link = document.createElement('a');
        link.download = 'solen-evolution-<?= date('Ymd') ?>.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
    } catch (err) {
        console.error('Capture failed', err);
        alert('Could not generate card image. Try sharing the link instead.');
    } finally {
        if (buttons) buttons.style.display = 'flex';
        btn.disabled = false;
        btn.textContent = originalText;
    }
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert("Snapshot link copied! Share it with your circle.");
    });
}
</script>

</body>
</html>
