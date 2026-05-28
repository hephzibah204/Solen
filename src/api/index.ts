import { Hono } from 'hono';
import { cors } from 'hono/cors';
import { getCookie, setCookie, deleteCookie } from 'hono/cookie';
import { sign, verify } from 'hono/jwt';
import { getDb } from '../db/client';
import {
  users, coachProfiles, moodLogs, coachMemory,
  chatSessions, emotionEvents, ritualCompletions,
  timelineMilestones, growthSnapshots, auditLog,
  aiRateLimits, settings, nudgeLog,
  familyGroups, familyMembers, streakArticles,
  streakQuizzes, streakUserProgress
} from '../db/schema';
import { eq, and, desc, gte, sql, count } from 'drizzle-orm';

// ── Cloudflare Bindings Type ───────────────────────────────────────────────
export type Bindings = {
  DB: D1Database;
  AI: Ai;
  KV: KVNamespace;
  JWT_SECRET: string;
  ENVIRONMENT: string;
  SITE_URL: string;
  SITE_NAME: string;
};

type Variables = {
  userId: number;
  userEmail: string;
  userRole: string;
  userPlan: string;
};

const app = new Hono<{ Bindings: Bindings; Variables: Variables }>();

// ── CORS ───────────────────────────────────────────────────────────────────
app.use('/api/*', cors({
  origin: ['https://getsolen.com', 'http://localhost:5173'],
  allowMethods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
  allowHeaders: ['Content-Type', 'Authorization'],
  credentials: true,
}));

// ── JWT Auth Middleware ────────────────────────────────────────────────────
const requireAuth = async (c: any, next: any) => {
  const token = getCookie(c, 'solen_session');
  if (!token) return c.json({ error: 'Unauthorized' }, 401);
  try {
    const payload = await verify(token, c.env.JWT_SECRET ?? 'dev_secret_change_in_production') as any;
    c.set('userId', payload.id);
    c.set('userEmail', payload.email);
    c.set('userRole', payload.role);
    c.set('userPlan', payload.plan);
    await next();
  } catch {
    return c.json({ error: 'Session expired. Please log in again.' }, 401);
  }
};

// ── Helper: hash password (using Web Crypto available in Workers) ──────────
async function hashPassword(password: string): Promise<string> {
  const encoder = new TextEncoder();
  const data = encoder.encode(password);
  const hash = await crypto.subtle.digest('SHA-256', data);
  return Array.from(new Uint8Array(hash)).map(b => b.toString(16).padStart(2, '0')).join('');
}

async function verifyPassword(password: string, hash: string): Promise<boolean> {
  return (await hashPassword(password)) === hash;
}

// ── Helper: issue JWT and set cookie ─────────────────────────────────────
async function issueSession(c: any, user: any) {
  const payload = {
    id: user.id,
    email: user.email,
    role: user.role,
    plan: user.plan,
    exp: Math.floor(Date.now() / 1000) + 60 * 60 * 24 * 30,
  };
  const token = await sign(payload, c.env.JWT_SECRET ?? 'dev_secret_change_in_production');
  setCookie(c, 'solen_session', token, {
    httpOnly: true,
    secure: c.env.ENVIRONMENT === 'production',
    sameSite: 'Strict',
    path: '/',
    maxAge: 60 * 60 * 24 * 30,
  });
  return token;
}

// ── Smart AI Model Router with Auto Fallback ──────────────────────────────
async function runAiTextWithFallback(
  ai: any,
  messages: any[],
  systemPrompt: string,
  stream: boolean = false,
  lastUserMessage: string = ''
) {
  // Determine primary model based on length (smart routing)
  let primaryModel = '@cf/meta/llama-3.1-8b-instruct';
  const cleanMsg = lastUserMessage.trim();
  if (cleanMsg.length > 0 && cleanMsg.length < 20) {
    primaryModel = '@cf/meta/llama-3-8b-instruct';
  }

  const modelOrder = [
    primaryModel,
    '@cf/meta/llama-3.1-8b-instruct',
    '@cf/qwen/qwen1.5-14b-chat',
    '@cf/meta/llama-3-8b-instruct'
  ];
  
  // Deduplicate
  const uniqueModels = Array.from(new Set(modelOrder));
  let lastError: any = null;

  for (const model of uniqueModels) {
    try {
      console.log(`[Smart AI Router] Routing query to model: ${model}`);
      const res = await ai.run(model, {
        messages: [{ role: 'system', content: systemPrompt }, ...messages],
        stream,
        max_tokens: stream ? 1024 : 600
      });
      return { res, modelUsed: model };
    } catch (err) {
      console.warn(`[Smart AI Router] Model ${model} failed. Falling back. Error:`, err);
      lastError = err;
    }
  }
  throw new Error(`All Workers AI LLM models failed. Last error: ${lastError?.message || lastError}`);
}

// ─────────────────────────────────────────────────────────────────────────
// AUTH ENDPOINTS
// ─────────────────────────────────────────────────────────────────────────


// POST /api/register
app.post('/api/register', async (c) => {
  const { name, email, password } = await c.req.json();
  if (!name || !email || !password) return c.json({ error: 'All fields required' }, 400);
  if (password.length < 8) return c.json({ error: 'Password must be at least 8 characters' }, 400);

  const db = getDb(c.env.DB);
  const existing = await db.select({ id: users.id }).from(users).where(eq(users.email, email.toLowerCase())).get();
  if (existing) return c.json({ error: 'An account with this email already exists' }, 409);

  const hashed = await hashPassword(password);
  const userId = await db.insert(users).values({
    name: name.trim(),
    email: email.toLowerCase(),
    password: hashed,
    role: 'user',
    plan: 'free',
  }).returning({ id: users.id }).get();

  // Create default coach profile
  await db.insert(coachProfiles).values({ userId: userId.id }).onConflictDoNothing();

  const user = await db.select().from(users).where(eq(users.id, userId.id)).get();
  await issueSession(c, user);

  return c.json({ message: 'Account created', user: { id: user!.id, name: user!.name, role: user!.role, plan: user!.plan } }, 201);
});

// POST /api/login
app.post('/api/login', async (c) => {
  const { email, password } = await c.req.json();
  if (!email || !password) return c.json({ error: 'Email and password required' }, 400);

  const db = getDb(c.env.DB);
  const user = await db.select().from(users).where(eq(users.email, email.toLowerCase())).get();
  if (!user) return c.json({ error: 'Invalid email or password' }, 401);

  const valid = await verifyPassword(password, user.password);
  if (!valid) return c.json({ error: 'Invalid email or password' }, 401);

  // Update last login
  await db.update(users).set({ lastLogin: new Date().toISOString() }).where(eq(users.id, user.id));

  await issueSession(c, user);
  return c.json({ message: 'Welcome back!', user: { id: user.id, name: user.name, email: user.email, role: user.role, plan: user.plan } });
});

// POST /api/logout
app.post('/api/logout', (c) => {
  deleteCookie(c, 'solen_session', { path: '/' });
  return c.json({ message: 'Logged out successfully' });
});

// GET /api/me
app.get('/api/me', requireAuth, async (c) => {
  const db = getDb(c.env.DB);
  const user = await db.select({
    id: users.id, name: users.name, email: users.email,
    role: users.role, plan: users.plan, createdAt: users.createdAt,
  }).from(users).where(eq(users.id, c.get('userId'))).get();
  if (!user) return c.json({ error: 'User not found' }, 404);

  const profile = await db.select().from(coachProfiles).where(eq(coachProfiles.userId, user.id)).get();
  return c.json({ user, profile });
});

// ─────────────────────────────────────────────────────────────────────────
// AI WELLNESS COACH — Cloudflare Workers AI
// ─────────────────────────────────────────────────────────────────────────

// POST /api/chat
app.post('/api/chat', requireAuth, async (c) => {
  const userId = c.get('userId');
  const plan = c.get('userPlan');
  const db = getDb(c.env.DB);

  // Rate limiting
  const today = new Date().toISOString().slice(0, 10);
  const limitRow = await db.select().from(aiRateLimits)
    .where(and(eq(aiRateLimits.userId, userId), eq(aiRateLimits.limitDate, today))).get();

  const dailyLimit = plan === 'free' ? 20 : plan === 'plus' ? 100 : 500;
  if (limitRow && limitRow.requestCount >= dailyLimit) {
    return c.json({ error: `Daily AI limit reached (${dailyLimit} messages). Upgrade to continue.` }, 429);
  }

  const { messages, moodContext } = await c.req.json();
  if (!messages?.length) return c.json({ error: 'No messages provided' }, 400);

  // Load coach memory
  const memories = await db.select().from(coachMemory)
    .where(eq(coachMemory.userId, userId))
    .orderBy(desc(coachMemory.createdAt))
    .limit(4);

  const profile = await db.select().from(coachProfiles)
    .where(eq(coachProfiles.userId, userId)).get();

  // Build rich system prompt
  const memoryContext = memories.length
    ? `\n\nRecent memory from past sessions:\n${memories.map(m => `- ${m.summary}`).join('\n')}`
    : '';

  const moodCtx = moodContext ? `\n\nUser's current mood: ${moodContext}` : '';

  const systemPrompt = `You are Solen, an empathetic, warm, and highly intelligent AI wellness coach specialising in emotional wellbeing and recovery support. You remember this user across sessions and build on past conversations.

User profile:
- Name: ${profile?.coachName || 'Friend'}
- Focus: ${profile?.challenge || 'general wellness'}
- Tone preference: ${profile?.tone || 'gentle'}
- Growth stage: ${profile?.growthStage || 'exploration'}
${memoryContext}${moodCtx}

Guidelines:
- Be warm, human, and conversational — not clinical
- Ask one thoughtful follow-up question per response
- If you detect distress, gently suggest professional support
- Keep responses concise (2-4 paragraphs max) unless asked to elaborate
- Reference past sessions naturally when relevant`;

  // Increment rate limit
  await db.insert(aiRateLimits)
    .values({ userId, limitDate: today, requestCount: 1 })
    .onConflictDoUpdate({
      target: [aiRateLimits.userId, aiRateLimits.limitDate],
      set: { requestCount: sql`request_count + 1` },
    });

  const lastUserMessage = messages[messages.length - 1]?.content || '';

  // Real-time Sentiment Analysis & Proactive Mood Logging
  let detectedMoodScore: number | null = null;
  let detectedMoodLabel: string | null = null;
  let detectedMoodEmoji: string | null = null;

  try {
    if (lastUserMessage.trim().length > 3) {
      const sentiment = await c.env.AI.run('@cf/huggingface/distilbert-sst-2-int8', {
        text: lastUserMessage
      }) as any[];

      if (sentiment && sentiment.length > 0) {
        // Find highest score sentiment
        const topSentiment = sentiment.reduce((prev, current) => (prev.score > current.score) ? prev : current);
        const isPositive = topSentiment.label === 'POSITIVE';
        const scoreVal = topSentiment.score;

        if (isPositive) {
          detectedMoodScore = Math.round(5 + scoreVal * 5);
        } else {
          detectedMoodScore = Math.round(5 - scoreVal * 4);
        }

        detectedMoodScore = Math.max(1, Math.min(10, detectedMoodScore));

        if (detectedMoodScore >= 8) {
          detectedMoodEmoji = '😊';
          detectedMoodLabel = 'Positive & Upbeat';
        } else if (detectedMoodScore >= 6) {
          detectedMoodEmoji = '🙂';
          detectedMoodLabel = 'Calm & Content';
        } else if (detectedMoodScore >= 4) {
          detectedMoodEmoji = '😐';
          detectedMoodLabel = 'Neutral / Reflective';
        } else if (detectedMoodScore >= 2) {
          detectedMoodEmoji = '🙁';
          detectedMoodLabel = 'Stressed / Anxious';
        } else {
          detectedMoodEmoji = '😢';
          detectedMoodLabel = 'Struggling / Exhausted';
        }

        console.log(`[Auto-Sentiment] Detected mood ${detectedMoodScore}/10 (${detectedMoodLabel}) for message.`);
      }
    }
  } catch (sentimentErr) {
    console.warn('[Auto-Sentiment] Sentiment analysis failed:', sentimentErr);
  }

  // Auto-log mood if user hasn't logged mood manually today
  if (detectedMoodScore !== null && detectedMoodLabel !== null && detectedMoodEmoji !== null) {
    try {
      const alreadyLogged = await db.select({ id: moodLogs.id })
        .from(moodLogs)
        .where(and(eq(moodLogs.userId, userId), eq(moodLogs.loggedDate, today)))
        .limit(1)
        .get();

      if (!alreadyLogged) {
        await db.insert(moodLogs).values({
          userId,
          score: detectedMoodScore,
          label: detectedMoodLabel,
          emoji: detectedMoodEmoji,
          notes: `[Auto-logged via Coach Session]: "${lastUserMessage.slice(0, 60)}${lastUserMessage.length > 60 ? '...' : ''}"`,
          loggedDate: today
        });
        console.log(`[Auto-Sentiment] Auto-logged daily mood for user ${userId}.`);
      }

      // Capture emotional snapshot event
      await db.insert(emotionEvents).values({
        userId,
        state: detectedMoodLabel,
        score: detectedMoodScore,
        source: 'chat',
        indicators: JSON.stringify([detectedMoodLabel])
      });
    } catch (logErr) {
      console.error('[Auto-Sentiment] Failed to log emotion snapshot:', logErr);
    }
  }

  // Stream from Cloudflare Workers AI with Smart Routing & Fallback
  const { res: stream } = await runAiTextWithFallback(
    c.env.AI,
    messages,
    systemPrompt,
    true,
    lastUserMessage
  );

  return new Response(stream as ReadableStream, {
    headers: {
      'Content-Type': 'text/event-stream',
      'Cache-Control': 'no-cache',
      'Transfer-Encoding': 'chunked',
    },
  });
});

// ── Daily SoulArt Wallpaper Generator (Stable Diffusion) ───────────────────
app.post('/api/ai/generate-art', requireAuth, async (c) => {
  const userId = c.get('userId');
  const db = getDb(c.env.DB);

  try {
    const latestMood = await db.select()
      .from(moodLogs)
      .where(eq(moodLogs.userId, userId))
      .orderBy(desc(moodLogs.createdAt))
      .limit(1)
      .get();

    const score = latestMood?.score ?? 7;

    let generatedPrompt = "Serene minimalist mountain landscape with soft golden sunrise, high aesthetic watercolor illustration, pastel colors, calming, peaceful wallpaper";
    if (score >= 9) {
      generatedPrompt = "Serene, luminous watercolor of a blooming sunlit meadow, gold and pastel blue hues, radiant ambient light, high aesthetic minimalist landscape, calm and joyful vibes, ultra-clean design, mobile wallpaper, 1080x1920";
    } else if (score >= 7) {
      generatedPrompt = "Calming minimalist mist-covered lake at twilight, soft violet and warm gold gradient sky, serene reflection, highly peaceful ambient light, high aesthetic clean watercolor art, mobile wallpaper, 1080x1920";
    } else if (score >= 5) {
      generatedPrompt = "A serene minimalist path winding through light green birch trees, early morning sunlight, warm breeze, soft ambient aesthetic watercolor painting, clean balance, calming art, mobile wallpaper, 1080x1920";
    } else if (score >= 3) {
      generatedPrompt = "A serene and soothing abstract gradient artwork, flowing warm terracotta and gentle beige colors, calming waves, peaceful aura, minimalist comforting design, warm light, high aesthetic, mobile wallpaper, 1080x1920";
    } else {
      generatedPrompt = "A soft, comforting glowing lighthouse on a gentle, calm sea under a starry velvet sky, warm yellow light beaming, cozy and ultra-soothing watercolor artwork, high aesthetic peace, mobile wallpaper, 1080x1920";
    }

    console.log(`[SoulArt AI] Generating art for mood score ${score} with prompt: ${generatedPrompt}`);

    const aiResponse = await c.env.AI.run('@cf/stabilityai/stable-diffusion-xl-base-1.0', {
      prompt: generatedPrompt
    });

    const buffer = await aiResponse.arrayBuffer();
    
    // Safe chunk-free buffer to base64 conversion
    let binary = '';
    const bytes = new Uint8Array(buffer);
    const len = bytes.byteLength;
    for (let i = 0; i < len; i++) {
      binary += String.fromCharCode(bytes[i]);
    }
    const base64Image = btoa(binary);

    return c.json({
      image: `data:image/png;base64,${base64Image}`,
      prompt: generatedPrompt,
      moodScore: score
    });
  } catch (err: any) {
    console.error('[SoulArt AI] Image generation failed:', err);
    return c.json({ error: 'Failed to generate calming artwork', details: err.message }, 500);
  }
});

// ── Speech-to-Text Whisper Transcriber ─────────────────────────────────────
app.post('/api/ai/transcribe', requireAuth, async (c) => {
  try {
    const audioBuffer = await c.req.arrayBuffer();
    if (!audioBuffer || audioBuffer.byteLength === 0) {
      return c.json({ error: 'No audio data received' }, 400);
    }

    console.log(`[Whisper Audio] Transcribing audio chunk of ${audioBuffer.byteLength} bytes`);

    const transcription = await c.env.AI.run('@cf/openai/whisper', {
      audio: [...new Uint8Array(audioBuffer)]
    }) as any;

    return c.json({ text: transcription.text || '' });
  } catch (err: any) {
    console.error('[Whisper Audio] Transcription failed:', err);
    return c.json({ error: 'Failed to transcribe audio note', details: err.message }, 500);
  }
});

// ─────────────────────────────────────────────────────────────────────────
// MOOD LOGGING
// ─────────────────────────────────────────────────────────────────────────

// GET /api/mood
app.get('/api/mood', requireAuth, async (c) => {
  const db = getDb(c.env.DB);
  const userId = c.get('userId');
  const days = parseInt(c.req.query('days') ?? '30');
  const since = new Date(Date.now() - days * 86400000).toISOString().slice(0, 10);

  const logs = await db.select().from(moodLogs)
    .where(and(eq(moodLogs.userId, userId), gte(moodLogs.loggedDate, since)))
    .orderBy(desc(moodLogs.loggedDate))
    .all();

  return c.json({ moods: logs });
});

// POST /api/mood
app.post('/api/mood', requireAuth, async (c) => {
  const userId = c.get('userId');
  const { score, label, emoji, notes } = await c.req.json();
  if (!score || score < 1 || score > 10) return c.json({ error: 'Score must be 1-10' }, 400);

  const db = getDb(c.env.DB);
  const today = new Date().toISOString().slice(0, 10);

  await db.insert(moodLogs).values({ userId, score, label, emoji, notes, loggedDate: today });

  return c.json({ message: 'Mood logged', score, label, emoji });
});

// ─────────────────────────────────────────────────────────────────────────
// RITUALS
// ─────────────────────────────────────────────────────────────────────────

const DEFAULT_RITUALS = {
  morning: [
    { id: 'gratitude', title: 'Morning Gratitude', description: 'Write 3 things you are grateful for', icon: '🌅', duration: '5 min' },
    { id: 'breathwork', title: 'Box Breathing', description: '4 rounds of box breathing (4-4-4-4)', icon: '🫁', duration: '5 min' },
    { id: 'intention', title: 'Set Daily Intention', description: 'Choose one word that guides your day', icon: '🎯', duration: '2 min' },
  ],
  afternoon: [
    { id: 'checkin', title: 'Midday Check-in', description: 'How are you feeling right now?', icon: '🌤️', duration: '3 min' },
    { id: 'movement', title: 'Movement Break', description: '5 minutes of stretching or walking', icon: '🚶', duration: '5 min' },
  ],
  evening: [
    { id: 'reflection', title: 'Daily Reflection', description: 'What went well today? What could improve?', icon: '🌙', duration: '10 min' },
    { id: 'wind-down', title: 'Wind-Down Routine', description: 'Prepare your body and mind for rest', icon: '😴', duration: '10 min' },
  ],
};

// GET /api/rituals
app.get('/api/rituals', requireAuth, async (c) => {
  const userId = c.get('userId');
  const period = (c.req.query('period') ?? 'morning') as keyof typeof DEFAULT_RITUALS;
  const db = getDb(c.env.DB);
  const today = new Date().toISOString().slice(0, 10);

  const completed = await db.select({ ritualId: ritualCompletions.ritualId })
    .from(ritualCompletions)
    .where(and(
      eq(ritualCompletions.userId, userId),
      eq(ritualCompletions.completedDate, today),
      eq(ritualCompletions.period, period)
    )).all();

  const completedIds = new Set(completed.map(r => r.ritualId));
  const rituals = (DEFAULT_RITUALS[period] || DEFAULT_RITUALS.morning).map(r => ({
    ...r,
    completed: completedIds.has(r.id),
  }));

  return c.json({ rituals, period, date: today });
});

// POST /api/rituals/complete
app.post('/api/rituals/complete', requireAuth, async (c) => {
  const userId = c.get('userId');
  const { ritualId, period } = await c.req.json();
  const db = getDb(c.env.DB);
  const today = new Date().toISOString().slice(0, 10);

  await db.insert(ritualCompletions)
    .values({ userId, ritualId, period, completedDate: today })
    .onConflictDoNothing();

  return c.json({ message: 'Ritual completed!', ritualId, date: today });
});

// ─────────────────────────────────────────────────────────────────────────
// TIMELINE & GROWTH
// ─────────────────────────────────────────────────────────────────────────

// GET /api/timeline
app.get('/api/timeline', requireAuth, async (c) => {
  const userId = c.get('userId');
  const db = getDb(c.env.DB);
  const days = parseInt(c.req.query('days') ?? '30');
  const since = new Date(Date.now() - days * 86400000).toISOString();

  const [moods, milestones, snapshots] = await Promise.all([
    db.select().from(moodLogs)
      .where(and(eq(moodLogs.userId, userId), gte(moodLogs.createdAt, since)))
      .orderBy(desc(moodLogs.createdAt)).limit(60).all(),
    db.select().from(timelineMilestones)
      .where(eq(timelineMilestones.userId, userId))
      .orderBy(desc(timelineMilestones.earnedAt)).all(),
    db.select().from(growthSnapshots)
      .where(eq(growthSnapshots.userId, userId))
      .orderBy(desc(growthSnapshots.snapshotDate)).limit(30).all(),
  ]);

  const moodAvg = moods.length
    ? Math.round(moods.reduce((s, m) => s + m.score, 0) / moods.length * 10) / 10
    : null;

  return c.json({ moods, milestones, snapshots, moodAvg, days });
});

// ─────────────────────────────────────────────────────────────────────────
// PROFILE & SETTINGS
// ─────────────────────────────────────────────────────────────────────────

// GET /api/profile
app.get('/api/profile', requireAuth, async (c) => {
  const userId = c.get('userId');
  const db = getDb(c.env.DB);

  const [user, profile] = await Promise.all([
    db.select({ id: users.id, name: users.name, email: users.email, plan: users.plan, createdAt: users.createdAt })
      .from(users).where(eq(users.id, userId)).get(),
    db.select().from(coachProfiles).where(eq(coachProfiles.userId, userId)).get(),
  ]);

  return c.json({ user, profile });
});

// PUT /api/profile
app.put('/api/profile', requireAuth, async (c) => {
  const userId = c.get('userId');
  const { name, coachName, purpose, tone, challenge, personalityStyle } = await c.req.json();
  const db = getDb(c.env.DB);

  if (name) await db.update(users).set({ name }).where(eq(users.id, userId));

  await db.insert(coachProfiles)
    .values({ userId, coachName, purpose, tone, challenge, personalityStyle })
    .onConflictDoUpdate({
      target: coachProfiles.userId,
      set: { coachName, purpose, tone, challenge, personalityStyle, updatedAt: new Date().toISOString() },
    });

  return c.json({ message: 'Profile updated' });
});

// ─────────────────────────────────────────────────────────────────────────
// FAMILY SHARING SYSTEM
// ─────────────────────────────────────────────────────────────────────────
app.get('/api/family/my-group', requireAuth, async (c) => {
  const userId = c.get('userId');
  const plan = c.get('userPlan');
  const db = getDb(c.env.DB);

  const isPremium = ['premium', 'admin'].includes(plan);
  if (!isPremium) {
    return c.json({ error: 'Premium subscription required' }, 403);
  }

  const membership = await db.select()
    .from(familyMembers)
    .where(eq(familyMembers.userId, userId))
    .get();

  if (!membership) {
    return c.json({ group: null, members: [] });
  }

  const group = await db.select()
    .from(familyGroups)
    .where(eq(familyGroups.id, membership.groupId))
    .get();

  if (!group) {
    return c.json({ group: null, members: [] });
  }

  const membersList = await db.select({
    id: users.id,
    name: users.name,
    role: familyMembers.role,
    dayStreak: coachProfiles.dayStreak
  })
  .from(familyMembers)
  .innerJoin(users, eq(users.id, familyMembers.userId))
  .leftJoin(coachProfiles, eq(coachProfiles.userId, familyMembers.userId))
  .where(eq(familyMembers.groupId, group.id))
  .all();

  const userInGroup = membersList.find(m => m.id === userId);

  return c.json({
    group: {
      id: group.id,
      name: group.name,
      invite_code: group.inviteCode,
      role: userInGroup?.role || 'member'
    },
    members: membersList.map(m => ({ ...m, day_streak: m.dayStreak })),
    max: 4
  });
});

app.post('/api/family/create', requireAuth, async (c) => {
  const userId = c.get('userId');
  const plan = c.get('userPlan');
  const { name } = await c.req.json();
  const db = getDb(c.env.DB);

  if (!['premium', 'admin'].includes(plan)) {
    return c.json({ error: 'Premium subscription required' }, 403);
  }

  const existing = await db.select().from(familyMembers).where(eq(familyMembers.userId, userId)).get();
  if (existing) {
    return c.json({ error: 'Already a member of a group' }, 400);
  }

  const inviteCode = Math.random().toString(36).substring(2, 8).toUpperCase();

  const group = await db.insert(familyGroups).values({
    ownerId: userId,
    name: name || 'My Family',
    inviteCode
  }).returning({ id: familyGroups.id }).get();

  await db.insert(familyMembers).values({
    groupId: group.id,
    userId,
    role: 'owner'
  });

  return c.json({ ok: true });
});

app.post('/api/family/join', requireAuth, async (c) => {
  const userId = c.get('userId');
  const { code } = await c.req.json();
  const db = getDb(c.env.DB);

  if (!code) return c.json({ error: 'Invite code required' }, 400);

  const group = await db.select().from(familyGroups).where(eq(familyGroups.inviteCode, code.trim().toUpperCase())).get();
  if (!group) {
    return c.json({ error: 'Invalid invite code' }, 404);
  }

  const countRes = await db.select({ val: count() }).from(familyMembers).where(eq(familyMembers.groupId, group.id)).get();
  if (countRes && countRes.val >= 4) {
    return c.json({ error: 'Family group is full' }, 400);
  }

  const existing = await db.select().from(familyMembers).where(eq(familyMembers.userId, userId)).get();
  if (existing) {
    return c.json({ error: 'Already in a family group' }, 400);
  }

  await db.insert(familyMembers).values({
    groupId: group.id,
    userId,
    role: 'member'
  });

  return c.json({ ok: true });
});

app.post('/api/family/remove_member', requireAuth, async (c) => {
  const userId = c.get('userId');
  const { user_id } = await c.req.json();
  const db = getDb(c.env.DB);

  const selfMembership = await db.select().from(familyMembers).where(eq(familyMembers.userId, userId)).get();
  if (!selfMembership || selfMembership.role !== 'owner') {
    return c.json({ error: 'Only owners can remove members' }, 403);
  }

  await db.delete(familyMembers).where(and(eq(familyMembers.groupId, selfMembership.groupId), eq(familyMembers.userId, user_id)));
  return c.json({ ok: true });
});

app.post('/api/family/leave', requireAuth, async (c) => {
  const userId = c.get('userId');
  const db = getDb(c.env.DB);

  const membership = await db.select().from(familyMembers).where(eq(familyMembers.userId, userId)).get();
  if (!membership) return c.json({ error: 'Not in a group' }, 400);

  if (membership.role === 'owner') {
    await db.delete(familyMembers).where(eq(familyMembers.groupId, membership.groupId));
    await db.delete(familyGroups).where(eq(familyGroups.id, membership.groupId));
  } else {
    await db.delete(familyMembers).where(eq(familyMembers.userId, userId));
  }

  return c.json({ ok: true });
});

// ─────────────────────────────────────────────────────────────────────────
// WELLNESS INSIGHTS & INTEL
// ─────────────────────────────────────────────────────────────────────────
app.get('/api/insights', requireAuth, async (c) => {
  const userId = c.get('userId');
  const db = getDb(c.env.DB);

  const moods = await db.select({ score: moodLogs.score, date: moodLogs.loggedDate }).from(moodLogs)
    .where(eq(moodLogs.userId, userId))
    .orderBy(desc(moodLogs.loggedDate))
    .limit(7);

  const avgMood = moods.length
    ? Math.round((moods.reduce((s, m) => s + m.score, 0) / moods.length) * 10) / 10
    : null;

  const profile = await db.select().from(coachProfiles).where(eq(coachProfiles.userId, userId)).get();
  const epCount = await db.select({ val: count() }).from(coachMemory).where(eq(coachMemory.userId, userId)).get();

  const topInsights = await db.select().from(coachMemory)
    .where(eq(coachMemory.userId, userId))
    .orderBy(desc(coachMemory.createdAt))
    .limit(10);

  const allThemes: string[] = [];
  topInsights.forEach(i => {
    if (i.themes) {
      try {
        const parsed = JSON.parse(i.themes);
        if (Array.isArray(parsed)) allThemes.push(...parsed);
      } catch {
        i.themes.split(',').forEach(t => allThemes.push(t.trim()));
      }
    }
  });

  const counts: Record<string, number> = {};
  allThemes.forEach(t => { if (t) counts[t] = (counts[t] || 0) + 1; });
  const sortedThemes = Object.keys(counts).sort((a, b) => counts[b] - counts[a]).slice(0, 3);

  return c.json({
    streak: profile?.dayStreak ?? 0,
    avgMood,
    epCount: epCount?.val ?? 0,
    days: moods.length,
    frequentThemes: sortedThemes,
    insights: topInsights.map(i => ({ ...i, session_date: i.sessionDate }))
  });
});

app.post('/api/insights/life-intelligence', requireAuth, async (c) => {
  const userId = c.get('userId');
  const plan = c.get('userPlan');
  const db = getDb(c.env.DB);

  if (!['premium', 'admin'].includes(plan)) {
    return c.json({ error: 'Premium subscription required' }, 403);
  }

  const memories = await db.select().from(coachMemory)
    .where(eq(coachMemory.userId, userId))
    .orderBy(desc(coachMemory.createdAt))
    .limit(8);

  if (!memories.length) {
    return c.json({ intelligence: null });
  }

  const memoryContext = memories.map(m => `- ${m.summary} (${m.themes})`).join('\n');

  const systemPrompt = `You are a professional clinical psychology research analyzer. Given a user's recent emotional coaching summaries, output a JSON object containing:
1. "life_phase": A 3-5 word concise headline describing their current active life phase (e.g., "Seeking Balanced Grounding", "Navigating Transitional Urges").
2. "evolution": A beautiful, compassionate, 3-paragraph summary of their emotional development, progress milestones, stress cues, and supportive insights.

Your output must be strict, valid JSON. DO NOT write markdown backticks or extra text, just raw JSON.`;

  try {
    const { res: rawAi } = await runAiTextWithFallback(
      c.env.AI,
      [{ role: 'user', content: `Analyze these wellness diaries:\n${memoryContext}` }],
      systemPrompt,
      false,
      ''
    );

    let responseText = rawAi.response || '';

    responseText = responseText.replace(/```json/g, '').replace(/```/g, '').trim();
    const parsed = JSON.parse(responseText);

    return c.json({ intelligence: parsed });
  } catch (e) {
    return c.json({
      intelligence: {
        life_phase: "Steady Growth & Self Discovery",
        evolution: "You are actively showing up, prioritizing your daily rhythms, and building context across your reflections. Consistent check-ins are establishing a high-integrity emotional foundation."
      }
    });
  }
});

app.get('/api/insights/export', requireAuth, async (c) => {
  const userId = c.get('userId');
  const db = getDb(c.env.DB);

  const [moods, completions, milestones] = await Promise.all([
    db.select().from(moodLogs).where(eq(moodLogs.userId, userId)).orderBy(desc(moodLogs.loggedDate)).all(),
    db.select().from(ritualCompletions).where(eq(ritualCompletions.userId, userId)).orderBy(desc(ritualCompletions.completedDate)).all(),
    db.select().from(timelineMilestones).where(eq(timelineMilestones.userId, userId)).orderBy(desc(timelineMilestones.earnedAt)).all()
  ]);

  return c.json({
    exported_at: new Date().toISOString(),
    logs: {
      mood_diary: moods,
      habits_completed: completions,
      journey_milestones: milestones
    }
  });
});

// ─────────────────────────────────────────────────────────────────────────
// STREAK LEARNING & QUIZZES
// ─────────────────────────────────────────────────────────────────────────
app.get('/api/streak/today-article', requireAuth, async (c) => {
  const userId = c.get('userId');
  const db = getDb(c.env.DB);

  const profile = await db.select().from(coachProfiles).where(eq(coachProfiles.userId, userId)).get();
  const category = profile?.purpose || 'wellness';

  let article = await db.select({
    id: streakArticles.id,
    title: streakArticles.title,
    category: streakArticles.category,
    content: streakArticles.content,
    readTimeMin: streakArticles.readTimeMin
  })
  .from(streakArticles)
  .leftJoin(streakUserProgress, and(eq(streakUserProgress.articleId, streakArticles.id), eq(streakUserProgress.userId, userId)))
  .where(and(eq(streakArticles.category, category), sql`streak_user_progress.read_at IS NULL`))
  .get();

  if (!article) {
    article = await db.select({
      id: streakArticles.id,
      title: streakArticles.title,
      category: streakArticles.category,
      content: streakArticles.content,
      readTimeMin: streakArticles.readTimeMin
    })
    .from(streakArticles)
    .leftJoin(streakUserProgress, and(eq(streakUserProgress.articleId, streakArticles.id), eq(streakUserProgress.userId, userId)))
    .where(sql`streak_user_progress.read_at IS NULL`)
    .get();
  }

  if (!article) {
    article = await db.select().from(streakArticles).get();
  }

  if (!article) {
    return c.json({ article: null, quizzes: [], progress: null });
  }

  const quizzesList = await db.select().from(streakQuizzes).where(eq(streakQuizzes.articleId, article.id)).all();
  const decodedQuizzes = quizzesList.map(q => {
    try {
      return { ...q, options: JSON.parse(q.options) };
    } catch {
      return { ...q, options: [] };
    }
  });

  const progress = await db.select().from(streakUserProgress)
    .where(and(eq(streakUserProgress.articleId, article.id), eq(streakUserProgress.userId, userId)))
    .get();

  return c.json({
    article,
    quizzes: decodedQuizzes,
    progress: progress ?? null
  });
});

app.post('/api/streak/read-article', requireAuth, async (c) => {
  const userId = c.get('userId');
  const { article_id } = await c.req.json();
  const db = getDb(c.env.DB);

  if (!article_id) return c.json({ error: 'Article ID required' }, 400);

  await db.insert(streakUserProgress)
    .values({
      userId,
      articleId: article_id,
      readAt: new Date().toISOString()
    })
    .onConflictDoUpdate({
      target: [streakUserProgress.userId, streakUserProgress.articleId],
      set: { readAt: new Date().toISOString() }
    });

  return c.json({ ok: true });
});

app.post('/api/streak/submit-quiz', requireAuth, async (c) => {
  const userId = c.get('userId');
  const { article_id, answers } = await c.req.json();
  const db = getDb(c.env.DB);

  if (!article_id || !answers) {
    return c.json({ error: 'Article ID and answers required' }, 400);
  }

  const quizzesList = await db.select().from(streakQuizzes).where(eq(streakQuizzes.articleId, article_id)).all();
  
  let correct = 0;
  quizzesList.forEach(q => {
    if (answers[q.id] === q.correctIndex) {
      correct++;
    }
  });

  const total = quizzesList.length;
  const score = total > 0 ? Math.round((correct / total) * 100) : 0;
  const points = score >= 80 ? 10 : (score >= 50 ? 5 : 2);

  await db.insert(streakUserProgress)
    .values({
      userId,
      articleId: article_id,
      quizCompleted: 1,
      quizScore: score,
      pointsEarned: points,
      readAt: new Date().toISOString()
    })
    .onConflictDoUpdate({
      target: [streakUserProgress.userId, streakUserProgress.articleId],
      set: {
        quizCompleted: 1,
        quizScore: score,
        pointsEarned: points,
        readAt: new Date().toISOString()
      }
    });

  const explanations = quizzesList.map(q => ({
    quiz_id: q.id,
    correct_index: q.correctIndex,
    explanation: q.explanation || ''
  }));

  return c.json({
    ok: true,
    score,
    correct,
    total,
    points,
    explanations
  });
});

// ─────────────────────────────────────────────────────────────────────────
// DASHBOARD STATS
// ─────────────────────────────────────────────────────────────────────────

// GET /api/stats
app.get('/api/stats', requireAuth, async (c) => {
  const userId = c.get('userId');
  const db = getDb(c.env.DB);
  const today = new Date().toISOString().slice(0, 10);
  const week = new Date(Date.now() - 7 * 86400000).toISOString().slice(0, 10);

  const [profile, todayMood, weekMoods, todayRituals] = await Promise.all([
    db.select().from(coachProfiles).where(eq(coachProfiles.userId, userId)).get(),
    db.select().from(moodLogs)
      .where(and(eq(moodLogs.userId, userId), eq(moodLogs.loggedDate, today)))
      .orderBy(desc(moodLogs.createdAt)).limit(1).get(),
    db.select().from(moodLogs)
      .where(and(eq(moodLogs.userId, userId), gte(moodLogs.loggedDate, week))).all(),
    db.select().from(ritualCompletions)
      .where(and(eq(ritualCompletions.userId, userId), eq(ritualCompletions.completedDate, today))).all(),
  ]);

  return c.json({
    streak: profile?.dayStreak ?? 0,
    todayMood: todayMood ?? null,
    weekMoodAvg: weekMoods.length
      ? Math.round(weekMoods.reduce((s, m) => s + m.score, 0) / weekMoods.length * 10) / 10
      : null,
    ritualsDone: todayRituals.length,
    plan: c.get('userPlan'),
    growthStage: profile?.growthStage ?? 'exploration',
  });
});

// ─────────────────────────────────────────────────────────────────────────
// HEALTH CHECK
// ─────────────────────────────────────────────────────────────────────────
app.get('/api/health', (c) => c.json({ status: 'ok', ts: Date.now() }));

// ─────────────────────────────────────────────────────────────────────────
// 404 fallback
// ─────────────────────────────────────────────────────────────────────────
app.notFound((c) => c.json({ error: 'Not found' }, 404));

export default app;
