-- ============================================================
-- Solen DB: Full Schema Migration for Cloudflare D1
-- Generated for: wrangler d1 migrations apply solen-db
-- ============================================================

CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  email TEXT UNIQUE NOT NULL,
  password TEXT NOT NULL,
  role TEXT NOT NULL DEFAULT 'user',
  plan TEXT NOT NULL DEFAULT 'free',
  trial_ends TEXT,
  created_at TEXT DEFAULT (datetime('now')),
  last_login TEXT,
  last_ip TEXT,
  stripe_customer_id TEXT
);
CREATE INDEX IF NOT EXISTS idx_users_last_login ON users(last_login);
CREATE INDEX IF NOT EXISTS idx_users_stripe_cid ON users(stripe_customer_id);

CREATE TABLE IF NOT EXISTS subscriptions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  plan TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'active',
  amount REAL DEFAULT 0,
  billing_cycle TEXT DEFAULT 'monthly',
  started_at TEXT DEFAULT (datetime('now')),
  expires_at TEXT,
  cancelled_at TEXT,
  notes TEXT
);
CREATE INDEX IF NOT EXISTS idx_subs_status_cycle ON subscriptions(status, billing_cycle);

CREATE TABLE IF NOT EXISTS blog_posts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL,
  slug TEXT UNIQUE NOT NULL,
  excerpt TEXT,
  content TEXT,
  meta_title TEXT,
  meta_desc TEXT,
  featured_image TEXT,
  status TEXT DEFAULT 'draft',
  author_id INTEGER REFERENCES users(id),
  category TEXT DEFAULT 'General',
  tags TEXT,
  views INTEGER DEFAULT 0,
  published_at TEXT,
  created_at TEXT DEFAULT (datetime('now')),
  updated_at TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS coach_profiles (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER UNIQUE NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  purpose TEXT,
  tone TEXT,
  challenge TEXT,
  coach_name TEXT,
  day_streak INTEGER DEFAULT 0,
  last_date TEXT,
  program_day INTEGER DEFAULT 0,
  updated_at TEXT DEFAULT (datetime('now')),
  notification_prefs TEXT DEFAULT '{}',
  program_completed_at TEXT,
  relationship_level INTEGER DEFAULT 1,
  personality_style TEXT DEFAULT 'gentle',
  growth_stage TEXT DEFAULT 'exploration',
  addiction_focus TEXT DEFAULT ''
);

CREATE TABLE IF NOT EXISTS mood_logs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  score INTEGER NOT NULL,
  label TEXT,
  emoji TEXT,
  notes TEXT DEFAULT '',
  logged_date TEXT DEFAULT (date('now')),
  created_at TEXT DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_mood_logs_user_date ON mood_logs(user_id, logged_date);

CREATE TABLE IF NOT EXISTS coach_memory (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  summary TEXT,
  themes TEXT,
  session_date TEXT DEFAULT (date('now')),
  created_at TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS settings (
  key TEXT PRIMARY KEY,
  value TEXT
);

CREATE TABLE IF NOT EXISTS user_sessions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  token TEXT UNIQUE NOT NULL,
  expires_at TEXT NOT NULL,
  created_at TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS audit_log (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
  action TEXT NOT NULL,
  detail TEXT,
  ip TEXT,
  created_at TEXT DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_audit_log_user ON audit_log(user_id, created_at);
CREATE INDEX IF NOT EXISTS idx_audit_log_action ON audit_log(action, created_at);

CREATE TABLE IF NOT EXISTS ai_rate_limits (
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  limit_date TEXT NOT NULL,
  request_count INTEGER DEFAULT 0,
  PRIMARY KEY (user_id, limit_date)
);

CREATE TABLE IF NOT EXISTS password_resets (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  token_hash TEXT NOT NULL,
  expires_at TEXT NOT NULL,
  used_at TEXT,
  created_at TEXT DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_password_resets_token ON password_resets(token_hash);

CREATE TABLE IF NOT EXISTS payment_transactions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  gateway TEXT NOT NULL,
  tx_ref TEXT,
  plan TEXT NOT NULL,
  billing TEXT DEFAULT 'monthly',
  amount REAL DEFAULT 0,
  status TEXT DEFAULT 'pending',
  created_at TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS chat_sessions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  session_date TEXT NOT NULL DEFAULT (date('now')),
  messages TEXT NOT NULL DEFAULT '[]',
  message_count INTEGER DEFAULT 0,
  created_at TEXT DEFAULT (datetime('now')),
  updated_at TEXT DEFAULT (datetime('now')),
  UNIQUE(user_id, session_date)
);
CREATE INDEX IF NOT EXISTS idx_chat_sessions_user ON chat_sessions(user_id, session_date);

CREATE TABLE IF NOT EXISTS emotion_events (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  state TEXT NOT NULL,
  score REAL DEFAULT 0,
  indicators TEXT DEFAULT '[]',
  source TEXT DEFAULT 'chat',
  created_at TEXT DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_emotion_events_user ON emotion_events(user_id, created_at);

CREATE TABLE IF NOT EXISTS nudge_log (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  nudge_hash TEXT NOT NULL,
  shown_at TEXT DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_nudge_log_user ON nudge_log(user_id);

CREATE TABLE IF NOT EXISTS ritual_completions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  ritual_id TEXT NOT NULL,
  completed_date TEXT NOT NULL,
  period TEXT NOT NULL DEFAULT 'morning',
  created_at TEXT DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_ritual_completions_user_date ON ritual_completions(user_id, completed_date);

CREATE TABLE IF NOT EXISTS ritual_preferences (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER UNIQUE NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  enabled_rituals TEXT DEFAULT '[]',
  reminder_time TEXT,
  updated_at TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS timeline_milestones (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  title TEXT NOT NULL,
  description TEXT,
  milestone_type TEXT,
  earned_at TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS reminder_schedules (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  ritual_id TEXT,
  scheduled_for TEXT,
  sent_at TEXT,
  status TEXT DEFAULT 'pending'
);

CREATE TABLE IF NOT EXISTS growth_snapshots (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  snapshot_date TEXT NOT NULL,
  mood_avg REAL,
  streak_count INTEGER DEFAULT 0,
  sessions_count INTEGER DEFAULT 0,
  rituals_count INTEGER DEFAULT 0,
  created_at TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS family_groups (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  owner_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  name TEXT NOT NULL DEFAULT 'My Family',
  invite_code TEXT UNIQUE NOT NULL,
  created_at TEXT DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_family_groups_owner ON family_groups(owner_id);

CREATE TABLE IF NOT EXISTS family_members (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  group_id INTEGER NOT NULL REFERENCES family_groups(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  role TEXT NOT NULL DEFAULT 'member',
  joined_at TEXT DEFAULT (datetime('now')),
  UNIQUE(group_id, user_id)
);

CREATE TABLE IF NOT EXISTS streak_articles (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL,
  slug TEXT UNIQUE NOT NULL,
  category TEXT NOT NULL DEFAULT 'wellness',
  content TEXT NOT NULL,
  read_time_min INTEGER DEFAULT 3,
  created_at TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS streak_quizzes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  article_id INTEGER NOT NULL REFERENCES streak_articles(id) ON DELETE CASCADE,
  question TEXT NOT NULL,
  options TEXT NOT NULL DEFAULT '[]',
  correct_index INTEGER NOT NULL DEFAULT 0,
  explanation TEXT DEFAULT ''
);

CREATE TABLE IF NOT EXISTS streak_user_progress (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  article_id INTEGER NOT NULL REFERENCES streak_articles(id) ON DELETE CASCADE,
  read_at TEXT,
  quiz_completed INTEGER DEFAULT 0,
  quiz_score INTEGER DEFAULT 0,
  points_earned INTEGER DEFAULT 0,
  created_at TEXT DEFAULT (datetime('now')),
  UNIQUE(user_id, article_id)
);
CREATE INDEX IF NOT EXISTS idx_streak_progress_user ON streak_user_progress(user_id);

CREATE TABLE IF NOT EXISTS user_push_subscriptions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  endpoint TEXT UNIQUE NOT NULL,
  p256dh TEXT NOT NULL,
  auth TEXT NOT NULL,
  created_at TEXT DEFAULT (datetime('now'))
);

-- ── DEFAULT SETTINGS ──────────────────────────────────────────────────────
INSERT OR IGNORE INTO settings (key, value) VALUES
  ('site_name',                   'Solen'),
  ('site_tagline',                'The AI Wellness Coach That Remembers You'),
  ('trial_days',                  '7'),
  ('maintenance_mode',            '0'),
  ('from_email',                  'hello@getsolen.com'),
  ('from_name',                   'Solen'),
  ('footer_text',                 '© 2026 Solen Inc. All rights reserved.'),
  ('stripe_pk',                   ''),
  ('stripe_sk',                   ''),
  ('stripe_webhook_secret',       ''),
  ('stripe_portal_url',           '#'),
  ('payment_gateway',             'stripe'),
  ('google_analytics',            ''),
  ('gtm_id',                      ''),
  ('fb_pixel',                    ''),
  ('meta_title_home',             'Solen — AI Wellness Coach That Remembers You'),
  ('meta_desc_home',              'Solen is your personal AI wellness coach. Get daily check-ins, mood tracking, and personalized support.'),
  ('sitemap_enabled',             '1'),
  ('robots_index',                '1'),
  ('ai_daily_limit_free',         '20'),
  ('ai_daily_limit_pro',          '200'),
  ('emotion_detection_enabled',   '1'),
  ('nudge_enabled',               '1'),
  ('rituals_enabled',             '1'),
  ('growth_dashboard_enabled',    '1'),
  ('analytics_snapshots_enabled', '1'),
  ('reminder_engine_enabled',     '1'),
  ('family_max_members',          '4'),
  ('streak_learning_enabled',     '1'),
  ('push_enabled',                '0'),
  ('vapid_public_key',            ''),
  ('vapid_private_key',           '');
