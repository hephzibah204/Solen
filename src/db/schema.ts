import { sqliteTable, text, integer, real, primaryKey } from 'drizzle-orm/sqlite-core';
import { sql } from 'drizzle-orm';

// ── USERS ─────────────────────────────────────────────────────────────────
export const users = sqliteTable('users', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  name: text('name').notNull(),
  email: text('email').notNull().unique(),
  password: text('password').notNull(),
  role: text('role').notNull().default('user'),
  plan: text('plan').notNull().default('free'),
  trialEnds: text('trial_ends'),
  createdAt: text('created_at').default(sql`(datetime('now'))`),
  lastLogin: text('last_login'),
  lastIp: text('last_ip'),
  stripeCustomerId: text('stripe_customer_id'),
});

// ── SUBSCRIPTIONS ─────────────────────────────────────────────────────────
export const subscriptions = sqliteTable('subscriptions', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  userId: integer('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  plan: text('plan').notNull(),
  status: text('status').notNull().default('active'),
  amount: real('amount').default(0),
  billingCycle: text('billing_cycle').default('monthly'),
  startedAt: text('started_at').default(sql`(datetime('now'))`),
  expiresAt: text('expires_at'),
  cancelledAt: text('cancelled_at'),
  notes: text('notes'),
});

// ── BLOG POSTS ────────────────────────────────────────────────────────────
export const blogPosts = sqliteTable('blog_posts', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  title: text('title').notNull(),
  slug: text('slug').notNull().unique(),
  excerpt: text('excerpt'),
  content: text('content'),
  metaTitle: text('meta_title'),
  metaDesc: text('meta_desc'),
  featuredImage: text('featured_image'),
  status: text('status').default('draft'),
  authorId: integer('author_id').references(() => users.id),
  category: text('category').default('General'),
  tags: text('tags'),
  views: integer('views').default(0),
  publishedAt: text('published_at'),
  createdAt: text('created_at').default(sql`(datetime('now'))`),
  updatedAt: text('updated_at').default(sql`(datetime('now'))`),
});

// ── COACH PROFILES ────────────────────────────────────────────────────────
export const coachProfiles = sqliteTable('coach_profiles', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  userId: integer('user_id').notNull().unique().references(() => users.id, { onDelete: 'cascade' }),
  purpose: text('purpose'),
  tone: text('tone'),
  challenge: text('challenge'),
  coachName: text('coach_name'),
  dayStreak: integer('day_streak').default(0),
  lastDate: text('last_date'),
  programDay: integer('program_day').default(0),
  updatedAt: text('updated_at').default(sql`(datetime('now'))`),
  notificationPrefs: text('notification_prefs').default('{}'),
  programCompletedAt: text('program_completed_at'),
  relationshipLevel: integer('relationship_level').default(1),
  personalityStyle: text('personality_style').default('gentle'),
  growthStage: text('growth_stage').default('exploration'),
  addictionFocus: text('addiction_focus').default(''),
});

// ── MOOD LOGS ─────────────────────────────────────────────────────────────
export const moodLogs = sqliteTable('mood_logs', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  userId: integer('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  score: integer('score').notNull(),
  label: text('label'),
  emoji: text('emoji'),
  notes: text('notes').default(''),
  loggedDate: text('logged_date').default(sql`(date('now'))`),
  createdAt: text('created_at').default(sql`(datetime('now'))`),
});

// ── COACH MEMORY ──────────────────────────────────────────────────────────
export const coachMemory = sqliteTable('coach_memory', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  userId: integer('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  summary: text('summary'),
  themes: text('themes'),
  sessionDate: text('session_date').default(sql`(date('now'))`),
  createdAt: text('created_at').default(sql`(datetime('now'))`),
});

// ── SETTINGS ──────────────────────────────────────────────────────────────
export const settings = sqliteTable('settings', {
  key: text('key').primaryKey(),
  value: text('value'),
});

// ── USER SESSIONS ─────────────────────────────────────────────────────────
export const userSessions = sqliteTable('user_sessions', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  userId: integer('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  token: text('token').notNull().unique(),
  expiresAt: text('expires_at').notNull(),
  createdAt: text('created_at').default(sql`(datetime('now'))`),
});

// ── AUDIT LOG ─────────────────────────────────────────────────────────────
export const auditLog = sqliteTable('audit_log', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  userId: integer('user_id').references(() => users.id, { onDelete: 'set null' }),
  action: text('action').notNull(),
  detail: text('detail'),
  ip: text('ip'),
  createdAt: text('created_at').default(sql`(datetime('now'))`),
});

// ── AI RATE LIMITS ────────────────────────────────────────────────────────
export const aiRateLimits = sqliteTable('ai_rate_limits', {
  userId: integer('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  limitDate: text('limit_date').notNull(),
  requestCount: integer('request_count').default(0),
}, (table) => ({
  pk: primaryKey({ columns: [table.userId, table.limitDate] }),
}));

// ── PASSWORD RESETS ───────────────────────────────────────────────────────
export const passwordResets = sqliteTable('password_resets', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  userId: integer('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  tokenHash: text('token_hash').notNull(),
  expiresAt: text('expires_at').notNull(),
  usedAt: text('used_at'),
  createdAt: text('created_at').default(sql`(datetime('now'))`),
});

// ── PAYMENT TRANSACTIONS ──────────────────────────────────────────────────
export const paymentTransactions = sqliteTable('payment_transactions', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  userId: integer('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  gateway: text('gateway').notNull(),
  txRef: text('tx_ref'),
  plan: text('plan').notNull(),
  billing: text('billing').default('monthly'),
  amount: real('amount').default(0),
  status: text('status').default('pending'),
  createdAt: text('created_at').default(sql`(datetime('now'))`),
});

// ── CHAT SESSIONS ─────────────────────────────────────────────────────────
export const chatSessions = sqliteTable('chat_sessions', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  userId: integer('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  sessionDate: text('session_date').notNull().default(sql`(date('now'))`),
  messages: text('messages').notNull().default('[]'),
  messageCount: integer('message_count').default(0),
  createdAt: text('created_at').default(sql`(datetime('now'))`),
  updatedAt: text('updated_at').default(sql`(datetime('now'))`),
});

// ── EMOTION EVENTS ────────────────────────────────────────────────────────
export const emotionEvents = sqliteTable('emotion_events', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  userId: integer('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  state: text('state').notNull(),
  score: real('score').default(0),
  indicators: text('indicators').default('[]'),
  source: text('source').default('chat'),
  createdAt: text('created_at').default(sql`(datetime('now'))`),
});

// ── NUDGE LOG ─────────────────────────────────────────────────────────────
export const nudgeLog = sqliteTable('nudge_log', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  userId: integer('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  nudgeHash: text('nudge_hash').notNull(),
  shownAt: text('shown_at').default(sql`(datetime('now'))`),
});

// ── RITUAL COMPLETIONS ────────────────────────────────────────────────────
export const ritualCompletions = sqliteTable('ritual_completions', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  userId: integer('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  ritualId: text('ritual_id').notNull(),
  completedDate: text('completed_date').notNull(),
  period: text('period').notNull().default('morning'),
  createdAt: text('created_at').default(sql`(datetime('now'))`),
});

// ── RITUAL PREFERENCES ────────────────────────────────────────────────────
export const ritualPreferences = sqliteTable('ritual_preferences', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  userId: integer('user_id').notNull().unique().references(() => users.id, { onDelete: 'cascade' }),
  enabledRituals: text('enabled_rituals').default('[]'),
  reminderTime: text('reminder_time'),
  updatedAt: text('updated_at').default(sql`(datetime('now'))`),
});

// ── TIMELINE MILESTONES ───────────────────────────────────────────────────
export const timelineMilestones = sqliteTable('timeline_milestones', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  userId: integer('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  title: text('title').notNull(),
  description: text('description'),
  milestoneType: text('milestone_type'),
  earnedAt: text('earned_at').default(sql`(datetime('now'))`),
});

// ── REMINDER SCHEDULES ────────────────────────────────────────────────────
export const reminderSchedules = sqliteTable('reminder_schedules', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  userId: integer('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  ritualId: text('ritual_id'),
  scheduledFor: text('scheduled_for'),
  sentAt: text('sent_at'),
  status: text('status').default('pending'),
});

// ── GROWTH SNAPSHOTS ──────────────────────────────────────────────────────
export const growthSnapshots = sqliteTable('growth_snapshots', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  userId: integer('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  snapshotDate: text('snapshot_date').notNull(),
  moodAvg: real('mood_avg'),
  streakCount: integer('streak_count').default(0),
  sessionsCount: integer('sessions_count').default(0),
  ritualsCount: integer('rituals_count').default(0),
  createdAt: text('created_at').default(sql`(datetime('now'))`),
});

// ── FAMILY GROUPS ─────────────────────────────────────────────────────────
export const familyGroups = sqliteTable('family_groups', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  ownerId: integer('owner_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  name: text('name').notNull().default('My Family'),
  inviteCode: text('invite_code').notNull().unique(),
  createdAt: text('created_at').default(sql`(datetime('now'))`),
});

export const familyMembers = sqliteTable('family_members', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  groupId: integer('group_id').notNull().references(() => familyGroups.id, { onDelete: 'cascade' }),
  userId: integer('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  role: text('role').notNull().default('member'),
  joinedAt: text('joined_at').default(sql`(datetime('now'))`),
});

// ── STREAK LEARNING ───────────────────────────────────────────────────────
export const streakArticles = sqliteTable('streak_articles', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  title: text('title').notNull(),
  slug: text('slug').notNull().unique(),
  category: text('category').notNull().default('wellness'),
  content: text('content').notNull(),
  readTimeMin: integer('read_time_min').default(3),
  createdAt: text('created_at').default(sql`(datetime('now'))`),
});

export const streakQuizzes = sqliteTable('streak_quizzes', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  articleId: integer('article_id').notNull().references(() => streakArticles.id, { onDelete: 'cascade' }),
  question: text('question').notNull(),
  options: text('options').notNull().default('[]'),
  correctIndex: integer('correct_index').notNull().default(0),
  explanation: text('explanation').default(''),
});

export const streakUserProgress = sqliteTable('streak_user_progress', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  userId: integer('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  articleId: integer('article_id').notNull().references(() => streakArticles.id, { onDelete: 'cascade' }),
  readAt: text('read_at'),
  quizCompleted: integer('quiz_completed').default(0),
  quizScore: integer('quiz_score').default(0),
  pointsEarned: integer('points_earned').default(0),
  createdAt: text('created_at').default(sql`(datetime('now'))`),
});

// ── PWA PUSH SUBSCRIPTIONS ────────────────────────────────────────────────
export const userPushSubscriptions = sqliteTable('user_push_subscriptions', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  userId: integer('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  endpoint: text('endpoint').notNull().unique(),
  p256dh: text('p256dh').notNull(),
  auth: text('auth').notNull(),
  createdAt: text('created_at').default(sql`(datetime('now'))`),
});
