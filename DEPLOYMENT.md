# Solen — Consolidated Package (Phase 3–6)

This is the fully consolidated deployment package combining:
- **Phase 3–4**: Advanced Memory, Voice/Realtime, AI Router, Payments
- **Phase 5–6**: Behavioral Retention System, Ritual UI, Growth Analytics Dashboard

All patches have been **pre-applied**. No manual file editing required.

---

## What's Included

### Phase 5 — Behavioral Retention System (new files)
| File | Purpose |
|---|---|
| `includes/retention.php` | Retention engine (rituals, timeline, analytics, reminders) |
| `api/retention.php` | User-facing retention API |
| `rituals.php` | Interactive daily ritual completion UI |
| `timeline.php` | Emotional timeline & growth analytics dashboard |

### Phase 6 — Premium UX (updates to above files)
- `rituals.php` — animated cards, mood picker, streak badge
- `timeline.php` — Chart.js mood trend visualisation, responsive

### Pre-applied patches
| File | What was patched |
|---|---|
| `includes/db.php` | Added `require_once 'retention.php'` + `retention_run_migrations($pdo)` |
| `admin/settings.php` | Added `'retention' => 'Retention'` tab + full Retention tab block + checkbox zero-out handler |

---

## Deployment Steps

### 1. Upload all files
Upload the full package to your server, replacing existing files.

The patched files (`includes/db.php`, `admin/settings.php`) are ready to go.

### 2. Enable in Admin
Admin → **Retention** tab:
- ✅ Enable Daily Rituals
- ✅ Enable Growth Dashboard
- ✅ Nightly Analytics Snapshots
- ✅ Adaptive Reminder Engine

(All 4 default to `1` in the DB seed — they'll be ON after first DB init.)

### 3. Optional: Add nav links
In `dashboard.php` and `app.php` nav sections:
```html
<a href="/rituals.php"  class="nav-link">Rituals</a>
<a href="/timeline.php" class="nav-link">Growth</a>
```

### 4. Cron (unchanged)
```bash
0 1 * * * curl -s -H "X-Cron-Secret: YOUR_SECRET" https://yourdomain.com/api/cron.php >> /var/log/solen-cron.log 2>&1
```
Jobs 7 (analytics snapshots), 8 (reminder processing), 9 (reminder scheduling) run automatically.

---

## New DB Tables (auto-created on first request)
- `ritual_completions`
- `ritual_preferences`
- `timeline_milestones`
- `reminder_schedules`
- `growth_snapshots`

---

## API Endpoints (Phase 5)
| Endpoint | Description |
|---|---|
| `GET /api/retention.php?action=rituals&period=morning` | Ritual list with completion state |
| `POST /api/retention.php?action=complete_ritual` | Mark ritual done |
| `GET /api/retention.php?action=streak` | Streak stats |
| `GET /api/retention.php?action=timeline&days=90` | Emotional timeline |
| `GET /api/retention.php?action=analytics` | Growth summary |
| `GET /api/retention.php?action=milestones` | Milestone moments |
