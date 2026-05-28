#!/usr/bin/env node
/**
 * Solen Data Migration Script
 * Exports data from legacy solen.db → migration-seed.sql
 * Ready to run with: wrangler d1 execute solen-db --file=database/migration-seed.sql --remote
 *
 * Usage:
 *   node scripts/export-sqlite-to-d1.js
 *
 * Prerequisites:
 *   npm install better-sqlite3
 */

const path = require('path');
const fs   = require('fs');

let Database;
try {
  Database = require('better-sqlite3');
} catch {
  console.error('❌  better-sqlite3 not installed. Run: npm install better-sqlite3');
  process.exit(1);
}

const DB_PATH  = path.join(__dirname, '..', 'database', 'solen.db');
const OUT_PATH = path.join(__dirname, '..', 'database', 'migration-seed.sql');

if (!fs.existsSync(DB_PATH)) {
  console.error(`❌  Database not found at: ${DB_PATH}`);
  console.error('    Make sure the legacy PHP app has been run at least once to create solen.db');
  process.exit(1);
}

const db = new Database(DB_PATH, { readonly: true });
const lines = [];

// ── Header ────────────────────────────────────────────────────────────────
lines.push(`-- ============================================================`);
lines.push(`-- Solen Data Migration: exported from solen.db`);
lines.push(`-- Generated: ${new Date().toISOString()}`);
lines.push(`-- Apply with: wrangler d1 execute solen-db --file=database/migration-seed.sql --remote`);
lines.push(`-- ============================================================`);
lines.push('');

// ── Helper: escape SQL string ─────────────────────────────────────────────
function esc(val) {
  if (val === null || val === undefined) return 'NULL';
  if (typeof val === 'number') return val.toString();
  return `'${String(val).replace(/'/g, "''")}'`;
}

// ── Helper: check table exists ────────────────────────────────────────────
function tableExists(name) {
  const row = db.prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?").get(name);
  return !!row;
}

// ── Export table ──────────────────────────────────────────────────────────
function exportTable(tableName, columns, skipCols = []) {
  if (!tableExists(tableName)) {
    console.log(`  ⚠️  Table "${tableName}" not found — skipping`);
    return 0;
  }

  const rows = db.prepare(`SELECT * FROM ${tableName}`).all();
  if (!rows.length) {
    console.log(`  ℹ️  Table "${tableName}" is empty — skipping`);
    return 0;
  }

  const exportCols = columns.filter(c => !skipCols.includes(c));
  lines.push(`-- Table: ${tableName} (${rows.length} rows)`);

  for (const row of rows) {
    const vals = exportCols.map(col => {
      // Redact password — replace with a locked hash placeholder
      if (col === 'password') return esc('__MIGRATED_RESET_REQUIRED__');
      return esc(row[col] ?? null);
    });
    lines.push(
      `INSERT OR IGNORE INTO ${tableName} (${exportCols.join(', ')}) VALUES (${vals.join(', ')});`
    );
  }

  lines.push('');
  return rows.length;
}

// ── Run Exports ───────────────────────────────────────────────────────────
console.log('🚀  Solen SQLite → D1 Migration Export\n');
console.log(`📂  Source: ${DB_PATH}`);
console.log(`📄  Output: ${OUT_PATH}\n`);

const tables = [
  {
    name: 'users',
    cols: ['id','name','email','password','role','plan','trial_ends','created_at','last_login','last_ip','stripe_customer_id'],
    note: '⚠️  Passwords are redacted — users must reset their passwords after migration',
  },
  { name: 'subscriptions',       cols: ['id','user_id','plan','status','amount','billing_cycle','started_at','expires_at','cancelled_at','notes'] },
  { name: 'blog_posts',          cols: ['id','title','slug','excerpt','content','meta_title','meta_desc','featured_image','status','author_id','category','tags','views','published_at','created_at','updated_at'] },
  { name: 'coach_profiles',      cols: ['id','user_id','purpose','tone','challenge','coach_name','day_streak','last_date','program_day','updated_at','notification_prefs','relationship_level','personality_style','growth_stage','addiction_focus'] },
  { name: 'mood_logs',           cols: ['id','user_id','score','label','emoji','notes','logged_date','created_at'] },
  { name: 'coach_memory',        cols: ['id','user_id','summary','themes','session_date','created_at'] },
  { name: 'settings',            cols: ['key','value'] },
  { name: 'chat_sessions',       cols: ['id','user_id','session_date','messages','message_count','created_at','updated_at'] },
  { name: 'emotion_events',      cols: ['id','user_id','state','score','indicators','source','created_at'] },
  { name: 'payment_transactions',cols: ['id','user_id','gateway','tx_ref','plan','billing','amount','status','created_at'] },
  { name: 'streak_articles',     cols: ['id','title','slug','category','content','read_time_min','created_at'] },
  { name: 'streak_quizzes',      cols: ['id','article_id','question','options','correct_index','explanation'] },
  { name: 'timeline_milestones', cols: ['id','user_id','title','description','milestone_type','earned_at'] },
];

let total = 0;
for (const t of tables) {
  process.stdout.write(`  Exporting ${t.name.padEnd(25)}`);
  const count = exportTable(t.name, t.cols);
  console.log(`→ ${count} rows ${t.note ? '\n  ' + t.note : ''}`);
  total += count;
}

// ── Write Output ──────────────────────────────────────────────────────────
fs.writeFileSync(OUT_PATH, lines.join('\n'), 'utf8');

console.log(`\n✅  Export complete! ${total} total rows written to:\n    ${OUT_PATH}`);
console.log('\n📋  Next steps:');
console.log('    1. Review the SQL file at database/migration-seed.sql');
console.log('    2. Apply schema first:  wrangler d1 migrations apply solen-db --remote');
console.log('    3. Apply data:          wrangler d1 execute solen-db --file=database/migration-seed.sql --remote');
console.log('    4. Notify users to reset passwords (passwords were redacted for security)\n');

db.close();
