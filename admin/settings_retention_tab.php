<?php
/**
 * Admin Settings — Phase 5/6 tab additions
 *
 * ADD these two tab entries to the existing $tabs array in admin/settings.php:
 *
 *   'retention' => 'Retention'
 *   (analytics_snapshots_enabled, adaptive_reminders_enabled sit here)
 *
 * This file is a DROP-IN PATCH — copy the two tab blocks below into
 * admin/settings.php, inserting them after the existing 'voice' block
 * and before the 'seo' block.
 *
 * ── PATCH INSTRUCTIONS ────────────────────────────────────────────────────
 *
 * 1. In admin/settings.php, find:
 *      $tabs = ['general'=>'General', ... 'voice'=>'Voice', 'seo'=>'SEO', ...];
 *    Change to:
 *      $tabs = ['general'=>'General', ... 'voice'=>'Voice', 'retention'=>'Retention', 'seo'=>'SEO', ...];
 *
 * 2. After the closing `<?php elseif ($tab === 'voice'): ?>` block's `<?php endif ?>`,
 *    paste the block below marked ── RETENTION TAB ──
 *
 * 3. No DB changes needed — retention_run_migrations() is called from
 *    includes/db.php after this patch is applied (see db_patch below).
 */

// ── DB PATCH (add to includes/db.php, after voice_run_migrations call) ───────
// retention_run_migrations($pdo);
// (Requires: require_once __DIR__ . '/retention.php'; at top of db.php)

// ────────────────────────────────────────────────────────────────────────────
// PASTE THE FOLLOWING INTO admin/settings.php (after voice tab, before seo tab)
// ────────────────────────────────────────────────────────────────────────────
?>

<?php /* ── RETENTION TAB ──────────────────────────────────────────────── */ ?>
<?php if ($tab === 'retention'): ?>

<h2 style="font-size:17px;font-weight:600;margin-bottom:20px">Behavioral Retention</h2>

<!-- Ritual System -->
<div style="background:rgba(255,255,255,0.02);border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:20px">
  <div style="font-weight:600;margin-bottom:16px;font-size:14px">🌅 Ritual System</div>

  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
    <div>
      <div style="font-weight:500;margin-bottom:3px">Enable Daily Rituals</div>
      <div style="font-size:13px;color:var(--muted)">Morning, evening, and weekly ritual check-ins with completion tracking and streak rewards.</div>
    </div>
    <label style="cursor:pointer">
      <input type="checkbox" name="rituals_enabled" value="1" <?= $s('rituals_enabled')==='1'?'checked':'' ?>>
    </label>
  </div>

  <div style="display:flex;align-items:center;justify-content:space-between">
    <div>
      <div style="font-weight:500;margin-bottom:3px">Show Ritual Link in Navigation</div>
      <div style="font-size:13px;color:var(--muted)">Add a "Rituals" link to the main nav so users can easily access their daily practices.</div>
    </div>
    <label style="cursor:pointer">
      <input type="checkbox" name="rituals_nav_enabled" value="1" <?= $s('rituals_nav_enabled')==='1'?'checked':'' ?>>
    </label>
  </div>
</div>

<!-- Emotional Timeline -->
<div style="background:rgba(255,255,255,0.02);border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:20px">
  <div style="font-weight:600;margin-bottom:16px;font-size:14px">📊 Emotional Timeline</div>

  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
    <div>
      <div style="font-weight:500;margin-bottom:3px">Enable Growth Dashboard</div>
      <div style="font-size:13px;color:var(--muted)">Users can view their emotional timeline, mood trend charts, and milestone moments at /timeline.php.</div>
    </div>
    <label style="cursor:pointer">
      <input type="checkbox" name="timeline_enabled" value="1" <?= $s('timeline_enabled')==='1'?'checked':'' ?>>
    </label>
  </div>
</div>

<!-- Growth Analytics -->
<div style="background:rgba(255,255,255,0.02);border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:20px">
  <div style="font-weight:600;margin-bottom:16px;font-size:14px">🌟 Growth Analytics</div>

  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
    <div>
      <div style="font-weight:500;margin-bottom:3px">Nightly Analytics Snapshots</div>
      <div style="font-size:13px;color:var(--muted)">Compute daily growth scores, mood trends, and consistency metrics for all active users. Run via cron (Job 7).</div>
    </div>
    <label style="cursor:pointer">
      <input type="checkbox" name="analytics_snapshots_enabled" value="1" <?= $s('analytics_snapshots_enabled')==='1'?'checked':'' ?>>
    </label>
  </div>
</div>

<!-- Smart Reminders -->
<div style="background:rgba(255,255,255,0.02);border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:20px">
  <div style="font-weight:600;margin-bottom:16px;font-size:14px">⏰ Smart Reminders</div>

  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
    <div>
      <div style="font-weight:500;margin-bottom:3px">Adaptive Reminder Engine</div>
      <div style="font-size:13px;color:var(--muted)">Send intelligent, emotionally-aware reminders based on user patterns — ritual nudges, inactivity re-engagement, stress support emails.</div>
    </div>
    <label style="cursor:pointer">
      <input type="checkbox" name="adaptive_reminders_enabled" value="1" <?= $s('adaptive_reminders_enabled')==='1'?'checked':'' ?>>
    </label>
  </div>

  <div style="background:rgba(184,149,106,0.06);border:1px solid rgba(184,149,106,0.15);border-radius:8px;padding:14px;font-size:13px;color:var(--muted);line-height:1.6">
    <strong style="color:var(--text)">Reminder types:</strong>
    <ul style="margin-top:6px;padding-left:16px">
      <li><strong>Morning ritual</strong> — sent at 9 AM if morning check-in not done</li>
      <li><strong>Evening ritual</strong> — sent at 8 PM if evening reflection not done</li>
      <li><strong>Inactivity</strong> — gentle re-engagement after 2+ days away</li>
      <li><strong>Stress support</strong> — empathetic nudge when burnout/anxiety patterns detected</li>
    </ul>
    <div style="margin-top:8px">Requires SMTP configured (Email tab) and cron running (Jobs 8 & 9).</div>
  </div>
</div>

<!-- Cron notes -->
<div style="background:rgba(255,255,255,0.02);border:1px solid var(--border);border-radius:12px;padding:20px">
  <div style="font-weight:600;margin-bottom:12px;font-size:14px">🕐 Cron Jobs (Phase 5)</div>
  <div style="font-size:13px;color:var(--muted);line-height:1.8">
    Three new jobs are added to <code style="color:var(--accent)">api/cron.php</code> by this phase:<br>
    <strong style="color:var(--text)">Job 7</strong> — Growth analytics snapshots (all active users)<br>
    <strong style="color:var(--text)">Job 8</strong> — Process due adaptive reminders (send emails)<br>
    <strong style="color:var(--text)">Job 9</strong> — Schedule tomorrow's reminders (based on user patterns)<br><br>
    Your existing cron entry (run once daily) will pick these up automatically — no changes needed.
  </div>
</div>

<?php endif ?>

<?php
// ─────────────────────────────────────────────────────────────────────────────
// Also add 'retention' to the settings save handler in admin/settings.php.
// The save block already loops over all POST data via:
//   foreach ($_POST as $k => $v) set_setting($k, $v);
// So checkbox fields work automatically — nothing extra needed.
// ─────────────────────────────────────────────────────────────────────────────
?>
