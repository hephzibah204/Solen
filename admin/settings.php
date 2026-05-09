<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/admin_layout.php';
require_admin();

$tab = $_GET['tab'] ?? 'general';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) { flash('error','Invalid request.'); redirect('/admin/settings.php?tab='.$tab); }

    // Zero-out checkbox toggles not present in POST (unchecked = '0')
    $checkbox_keys = [
        'gemini_live_enabled','voice_journaling_enabled',
        'rituals_enabled','growth_dashboard_enabled',
        'analytics_snapshots_enabled','reminder_engine_enabled',
        'emotion_detection_enabled','nudge_enabled','memory_enabled',
        'memory_compress_enabled','ai_fallback_enabled',
        'checkin_reminders_enabled','sitemap_enabled','robots_index',
    ];
    foreach ($checkbox_keys as $ck) {
        if (!isset($_POST[$ck])) set_setting($ck, '0');
    }
    $keys = array_keys($_POST);
    foreach ($keys as $k) {
        if ($k === 'csrf') continue;
        set_setting($k, trim($_POST[$k] ?? ''));
    }
    flash('success', 'Settings saved.');
    redirect('/admin/settings.php?tab='.$tab);
}

$s = fn($k, $d='') => h(get_setting($k, $d));

admin_head('Settings'); admin_sidebar('settings');
?>
<div class="main">
  <div class="topbar">
    <div class="topbar-title">Settings</div>
  </div>
  <div class="content">
    <?php admin_flash() ?>

    <!-- Tab nav -->
    <div style="display:flex;gap:4px;margin-bottom:24px;border-bottom:1px solid var(--border);padding-bottom:0">
      <?php
      $tabs = ['general'=>'General','plans'=>'Plans & Pricing','email'=>'Email / SMTP','ai'=>'AI Providers','payments'=>'Payments','integrations'=>'Integrations','memory'=>'Memory','voice'=>'Voice','retention'=>'Retention','seo'=>'SEO','danger'=>'Danger Zone'];
      foreach ($tabs as $key => $label):
      ?>
        <a href="?tab=<?= $key ?>" style="padding:10px 18px;border-radius:8px 8px 0 0;font-size:13px;border:1px solid <?= $tab===$key?'var(--border)':'transparent' ?>;border-bottom:1px solid <?= $tab===$key?'var(--card)':'transparent' ?>;background:<?= $tab===$key?'var(--card)':'transparent' ?>;color:<?= $tab===$key?'var(--text)':'var(--muted)' ?>;margin-bottom:-1px"><?= $label ?></a>
      <?php endforeach ?>
    </div>

    <form method="POST">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>"/>

      <?php if ($tab === 'general'): ?>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
        <div class="card" style="grid-column:1/-1">
          <div class="card-header"><div class="card-title">Site Identity</div></div>
          <div class="form-grid">
            <div class="form-group">
              <label>Site Name</label>
              <input class="form-control" name="site_name" value="<?= $s('site_name','Solen') ?>"/>
            </div>
            <div class="form-group">
              <label>Tagline</label>
              <input class="form-control" name="site_tagline" value="<?= $s('site_tagline') ?>"/>
            </div>
            <div class="form-group">
              <label>Site URL</label>
              <input class="form-control" name="site_url" value="<?= $s('site_url', SITE_URL) ?>" placeholder="https://getsolen.com"/>
            </div>
            <div class="form-group">
              <label>Admin Email</label>
              <input class="form-control" type="email" name="admin_email" value="<?= $s('admin_email', ADMIN_EMAIL) ?>"/>
            </div>
            <div class="form-group" style="grid-column:1/-1">
              <label>Footer Text</label>
              <input class="form-control" name="footer_text" value="<?= $s('footer_text') ?>"/>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><div class="card-title">Free Trial</div></div>
          <div class="form-group">
            <label>Trial Duration (days)</label>
            <input class="form-control" type="number" name="trial_days" value="<?= $s('trial_days','7') ?>" min="1" max="90"/>
          </div>
          <p class="text-muted text-sm" style="margin-top:10px">New users automatically get this many days of free Premium access.</p>
        </div>

        <div class="card">
          <div class="card-header"><div class="card-title">Maintenance Mode</div></div>
          <div class="form-group">
            <label>Maintenance Mode</label>
            <select class="form-control" name="maintenance_mode">
              <option value="0" <?= get_setting('maintenance_mode')==='0'?'selected':'' ?>>Off — Site is live</option>
              <option value="1" <?= get_setting('maintenance_mode')==='1'?'selected':'' ?>>On — Show maintenance page</option>
            </select>
          </div>
          <div class="form-group" style="margin-top:12px">
            <label>Maintenance Message</label>
            <textarea class="form-control" name="maintenance_message" rows="2"><?= $s('maintenance_message','We\'ll be back shortly.') ?></textarea>
          </div>
        </div>
      </div>

      <?php elseif ($tab === 'plans'): ?>
      <div style="display:flex;flex-direction:column;gap:16px">
        <?php
        $planDefs = [
          'pro'     => ['Pro',     '12.99', '99.00',  'Unlimited chat, memory, mood tracking, all programs'],
          'premium' => ['Premium', '24.99', '179.00', 'Everything in Pro + voice sessions, priority speed, family sharing'],
        ];
        foreach ($planDefs as $pk => [$pname, $defMonth, $defYear, $desc]):
        ?>
        <div class="card">
          <div class="card-header">
            <div class="card-title">Solen <?= $pname ?></div>
            <?= plan_badge($pk) ?>
          </div>
          <p class="text-muted text-sm" style="margin-bottom:16px"><?= $desc ?></p>
          <div class="form-grid">
            <div class="form-group">
              <label>Monthly Price (USD)</label>
              <div style="display:flex;align-items:center;gap:8px">
                <span class="text-muted">$</span>
                <input class="form-control" type="number" step="0.01" name="price_<?= $pk ?>_monthly"
                       value="<?= $s('price_'.$pk.'_monthly', $defMonth) ?>"/>
              </div>
            </div>
            <div class="form-group">
              <label>Yearly Price (USD)</label>
              <div style="display:flex;align-items:center;gap:8px">
                <span class="text-muted">$</span>
                <input class="form-control" type="number" step="0.01" name="price_<?= $pk ?>_yearly"
                       value="<?= $s('price_'.$pk.'_yearly', $defYear) ?>"/>
              </div>
            </div>
            <div class="form-group" style="grid-column:1/-1">
              <label>Plan Description (shown on pricing page)</label>
              <input class="form-control" name="plan_desc_<?= $pk ?>"
                     value="<?= $s('plan_desc_'.$pk, $desc) ?>"/>
            </div>
          </div>
        </div>
        <?php endforeach ?>

        <div class="card">
          <div class="card-header"><div class="card-title">Pricing Page Copy</div></div>
          <div class="form-grid">
            <div class="form-group">
              <label>Pricing Section Headline</label>
              <input class="form-control" name="pricing_headline" value="<?= $s('pricing_headline','Start free. Stay because it works.') ?>"/>
            </div>
            <div class="form-group">
              <label>Pricing Section Subtext</label>
              <input class="form-control" name="pricing_subtext" value="<?= $s('pricing_subtext','Seven days free. No card. No commitment.') ?>"/>
            </div>
          </div>
        </div>
      </div>

      <?php elseif ($tab === 'email'): ?>
      <div class="card" style="max-width:600px">
        <div class="card-header"><div class="card-title">SMTP Configuration</div></div>
        <div class="form-grid">
          <div class="form-group">
            <label>SMTP Host</label>
            <input class="form-control" name="smtp_host" value="<?= $s('smtp_host') ?>" placeholder="smtp.sendgrid.net"/>
          </div>
          <div class="form-group">
            <label>SMTP Port</label>
            <input class="form-control" type="number" name="smtp_port" value="<?= $s('smtp_port','587') ?>"/>
          </div>
          <div class="form-group">
            <label>SMTP Username</label>
            <input class="form-control" name="smtp_user" value="<?= $s('smtp_user') ?>"/>
          </div>
          <div class="form-group">
            <label>SMTP Password</label>
            <input class="form-control" type="password" name="smtp_pass" value="<?= $s('smtp_pass') ?>" placeholder="(unchanged if blank)"/>
          </div>
          <div class="form-group">
            <label>From Email</label>
            <input class="form-control" type="email" name="from_email" value="<?= $s('from_email','hello@getsolen.com') ?>"/>
          </div>
          <div class="form-group">
            <label>From Name</label>
            <input class="form-control" name="from_name" value="<?= $s('from_name','Solen') ?>"/>
          </div>
        </div>
        <div style="margin-top:18px;padding:14px;background:var(--bg2);border-radius:10px;font-size:13px;color:var(--muted)">
          💡 Use <strong style="color:var(--text)">SendGrid</strong>, <strong style="color:var(--text)">Mailgun</strong>, or <strong style="color:var(--text)">Postmark</strong> for reliable delivery. Avoid Gmail SMTP in production.
        </div>
      </div>

      <?php elseif ($tab === 'integrations'): ?>
      <div style="display:flex;flex-direction:column;gap:16px;max-width:700px">
        <div class="card">
          <div class="card-header"><div class="card-title">Anthropic / Claude API</div></div>
          <div class="form-group">
            <label>API Key</label>
            <input class="form-control" type="password" name="claude_api_key"
                   value="<?= $s('claude_api_key') ?>" placeholder="sk-ant-…"/>
          </div>
          <p class="text-muted text-sm" style="margin-top:8px">Get your key at <a href="https://console.anthropic.com" target="_blank" style="color:var(--accent)">console.anthropic.com</a>. Also set <code style="color:var(--accent)">CLAUDE_API_KEY</code> in config.php for the streaming proxy.</p>
        </div>

        <div class="card">
          <div class="card-header"><div class="card-title">Stripe Payments</div></div>
          <div class="form-grid">
            <div class="form-group">
              <label>Publishable Key</label>
              <input class="form-control" name="stripe_pk" value="<?= $s('stripe_pk') ?>" placeholder="pk_live_…"/>
            </div>
            <div class="form-group">
              <label>Secret Key</label>
              <input class="form-control" type="password" name="stripe_sk" value="<?= $s('stripe_sk') ?>" placeholder="sk_live_…"/>
            </div>
            <div class="form-group">
              <label>Webhook Secret</label>
              <input class="form-control" type="password" name="stripe_webhook_secret" value="<?= $s('stripe_webhook_secret') ?>" placeholder="whsec_…"/>
            </div>
            <div class="form-group">
              <label>Pro Monthly Price ID</label>
              <input class="form-control" name="stripe_price_pro_monthly" value="<?= $s('stripe_price_pro_monthly') ?>" placeholder="price_…"/>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><div class="card-title">Analytics & SEO</div></div>
          <div class="form-grid">
            <div class="form-group">
              <label>Google Analytics ID</label>
              <input class="form-control" name="google_analytics" value="<?= $s('google_analytics') ?>" placeholder="G-XXXXXXXXXX"/>
            </div>
            <div class="form-group">
              <label>Google Tag Manager ID</label>
              <input class="form-control" name="gtm_id" value="<?= $s('gtm_id') ?>" placeholder="GTM-XXXXXXX"/>
            </div>
            <div class="form-group" style="grid-column:1/-1">
              <label>Custom Head Scripts <span class="text-muted">(injected into &lt;head&gt; on all pages)</span></label>
              <textarea class="form-control" name="custom_head_scripts" rows="4" style="font-family:monospace;font-size:12px"><?= $s('custom_head_scripts') ?></textarea>
            </div>
          </div>
        </div>
      </div>

      <?php elseif ($tab === 'ai'): ?>
      <div style="display:flex;flex-direction:column;gap:16px;max-width:700px">
        <div class="card">
          <div class="card-header"><div class="card-title">OpenAI (DALL-E & GPT)</div></div>
          <div class="form-group">
            <label>OpenAI API Key</label>
            <input class="form-control" type="password" name="openai_api_key" value="<?= $s('openai_api_key') ?>" placeholder="sk-…"/>
          </div>
          <p class="text-muted text-sm" style="margin-top:8px">Used for <strong>DALL-E 3</strong> image generation in automated blog posts.</p>
        </div>

        <div class="card">
          <div class="card-header"><div class="card-title">Google Gemini</div></div>
          <div class="form-group">
            <label>Gemini API Key</label>
            <input class="form-control" type="password" name="gemini_api_key" value="<?= $s('gemini_api_key') ?>"/>
          </div>
          <div class="form-group" style="margin-top:12px">
            <label>Default Model</label>
            <input class="form-control" name="gemini_model" value="<?= $s('gemini_model','gemini-1.5-flash') ?>"/>
          </div>
          <p class="text-muted text-sm" style="margin-top:8px">Used for <strong>Voice sessions</strong> and real-time audio.</p>
        </div>

        <div class="card">
          <div class="card-header"><div class="card-title">Anthropic Claude</div></div>
          <div class="form-group">
            <label>Claude API Key</label>
            <input class="form-control" type="password" name="claude_api_key" value="<?= $s('claude_api_key') ?>"/>
          </div>
          <div class="form-group" style="margin-top:12px">
            <label>Core Coaching Model</label>
            <input class="form-control" name="claude_model" value="<?= $s('claude_model','claude-3-5-sonnet-20241022') ?>"/>
          </div>
        </div>
      </div>

      <?php elseif ($tab === 'memory'): ?>
      <div style="display:flex;flex-direction:column;gap:16px;max-width:700px">
        <div class="card">
          <div class="card-header"><div class="card-title">Phase 3 — Advanced Memory System</div></div>
          <div class="form-group">
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
              <input type="checkbox" name="memory_enabled" value="1" <?= $s('memory_enabled')==='1'?'checked':'' ?>>
              Enable Advanced Memory (episodic + semantic + emotional)
            </label>
          </div>
          <div class="form-group" style="margin-top:12px">
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
              <input type="checkbox" name="memory_compress_enabled" value="1" <?= $s('memory_compress_enabled')==='1'?'checked':'' ?>>
              Enable Nightly Session Compression (requires cron job)
            </label>
          </div>
          <div class="form-group" style="margin-top:12px">
            <label>Max Memory Episodes in Prompt <span class="text-muted">(2–12 recommended)</span></label>
            <input class="form-control" name="memory_max_context" type="number" min="2" max="20"
                   value="<?= h($s('memory_max_context') ?: '8') ?>" style="max-width:100px"/>
          </div>
          <p class="text-muted text-sm" style="margin-top:8px">
            When enabled, Solen remembers breakthroughs, emotional triggers, goals, and session themes across conversations.
            Session compression runs nightly via <code>api/cron.php</code>.
          </p>
        </div>

        <div class="card">
          <div class="card-header"><div class="card-title">Cohere — Semantic Embeddings</div></div>
          <div class="form-group">
            <label>Cohere API Key</label>
            <input class="form-control" type="password" name="cohere_api_key"
                   value="<?= h($s('cohere_api_key')) ?>" placeholder="xxxxxxxxxxxxxxxxxxxxxxxx"/>
          </div>
          <div class="form-group" style="margin-top:12px">
            <label>Embedding Model</label>
            <select class="form-control" name="cohere_embed_model" style="max-width:360px">
              <option value="embed-english-light-v3.0" <?= $s('cohere_embed_model')==='embed-english-light-v3.0'?'selected':'' ?>>embed-english-light-v3.0 (fast, cheap)</option>
              <option value="embed-english-v3.0" <?= $s('cohere_embed_model')==='embed-english-v3.0'?'selected':'' ?>>embed-english-v3.0 (higher quality)</option>
              <option value="embed-multilingual-light-v3.0" <?= $s('cohere_embed_model')==='embed-multilingual-light-v3.0'?'selected':'' ?>>embed-multilingual-light-v3.0</option>
            </select>
          </div>
          <p class="text-muted text-sm" style="margin-top:8px">
            Cohere embeddings enable semantic memory search — Solen finds relevant memories by meaning, not just keywords.
            Without a key, Solen falls back to keyword matching (still works, just less accurate).
            Get a free key at <a href="https://cohere.com" target="_blank" style="color:var(--accent)">cohere.com</a>.
          </p>
        </div>

        <div class="card">
          <div class="card-header"><div class="card-title">Emotional Intelligence (Phase 2 + 3)</div></div>
          <div class="form-group">
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
              <input type="checkbox" name="emotion_detection_enabled" value="1" <?= $s('emotion_detection_enabled')==='1'?'checked':'' ?>>
              Enable Emotion Detection
            </label>
          </div>
          <div class="form-group" style="margin-top:8px">
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
              <input type="checkbox" name="nudge_enabled" value="1" <?= $s('nudge_enabled')==='1'?'checked':'' ?>>
              Enable Smart Emotional Nudges
            </label>
          </div>
          <div class="form-group" style="margin-top:8px">
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
              <input type="checkbox" name="crisis_log_enabled" value="1" <?= $s('crisis_log_enabled')==='1'?'checked':'' ?>>
              Log Crisis Events to Audit Log
            </label>
          </div>
        </div>
      </div>

      <?php elseif ($tab === 'voice'): ?>
      <div style="display:flex;flex-direction:column;gap:16px;max-width:700px">
        <div class="card">
          <div class="card-header"><div class="card-title">Phase 4 — Gemini Live Voice</div></div>
          <div class="form-group">
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
              <input type="checkbox" name="gemini_live_enabled" value="1" <?= $s('gemini_live_enabled')==='1'?'checked':'' ?>>
              Enable Gemini Live Voice Conversations
            </label>
          </div>
          <div class="form-group" style="margin-top:12px">
            <label>Gemini Live Model</label>
            <select class="form-control" name="gemini_live_model" style="max-width:360px">
              <option value="gemini-2.0-flash-live-001" <?= $s('gemini_live_model')==='gemini-2.0-flash-live-001'?'selected':'' ?>>gemini-2.0-flash-live-001 (recommended)</option>
              <option value="gemini-2.5-flash-preview-native-audio-dialog" <?= $s('gemini_live_model')==='gemini-2.5-flash-preview-native-audio-dialog'?'selected':'' ?>>gemini-2.5-flash-preview (higher quality)</option>
            </select>
          </div>
          <div class="form-group" style="margin-top:12px">
            <label>Default Voice Mode</label>
            <select class="form-control" name="voice_default_mode" style="max-width:200px">
              <option value="calm" <?= $s('voice_default_mode')==='calm'?'selected':'' ?>>🌿 Calm</option>
              <option value="encouraging" <?= $s('voice_default_mode')==='encouraging'?'selected':'' ?>>✨ Encouraging</option>
              <option value="bedtime" <?= $s('voice_default_mode')==='bedtime'?'selected':'' ?>>🌙 Bedtime</option>
              <option value="reflective" <?= $s('voice_default_mode')==='reflective'?'selected':'' ?>>💭 Reflective</option>
            </select>
          </div>
          <p class="text-muted text-sm" style="margin-top:8px">
            Requires a Gemini API key (set in AI Providers tab). Voice conversations use Gemini Live's
            bidirectional audio stream. <strong>HTTPS is required</strong> — the Gemini API key is
            passed to the client over a secure session token. Make sure your deployment uses SSL.
          </p>
        </div>

        <div class="card">
          <div class="card-header"><div class="card-title">Voice Journaling</div></div>
          <div class="form-group">
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
              <input type="checkbox" name="voice_journaling_enabled" value="1" <?= $s('voice_journaling_enabled')==='1'?'checked':'' ?>>
              Enable Voice Journaling
            </label>
          </div>
          <p class="text-muted text-sm" style="margin-top:8px">
            Users can record spoken reflections which are transcribed (via Web Speech API or Gemini),
            stored as memory episodes, and analysed for emotional patterns. No external transcription
            API needed — transcription happens in the browser.
          </p>
        </div>

        <div class="card">
          <div class="card-header"><div class="card-title">Streaming UX</div></div>
          <div class="form-group">
            <label>Typing Animation Speed</label>
            <select class="form-control" name="stream_typing_speed" style="max-width:200px">
              <option value="auto" <?= $s('stream_typing_speed')==='auto'?'selected':'' ?>>Auto (adapts to emotion)</option>
              <option value="slow" <?= $s('stream_typing_speed')==='slow'?'selected':'' ?>>Slow (always)</option>
              <option value="normal" <?= $s('stream_typing_speed')==='normal'?'selected':'' ?>>Normal</option>
              <option value="fast" <?= $s('stream_typing_speed')==='fast'?'selected':'' ?>>Fast</option>
            </select>
          </div>
          <p class="text-muted text-sm" style="margin-top:8px">
            <em>Auto</em> adapts the typing animation speed to the detected emotional state —
            slower for crisis/distress, normal for neutral/positive responses.      <?php elseif ($tab === 'retention'): ?>
      <div style="display:flex;flex-direction:column;gap:20px;max-width:700px">
        <div class="card">
          <div class="card-header"><div class="card-title">Behavioral Retention</div></div>
          
          <div style="display:flex;flex-direction:column;gap:20px">
            <!-- Rituals -->
            <div style="display:flex;align-items:center;justify-content:space-between">
              <div>
                <div style="font-weight:500">Daily Rituals</div>
                <div class="text-muted text-sm">Morning, evening, and weekly ritual check-ins with completion tracking.</div>
              </div>
              <label class="switch">
                <input type="checkbox" name="rituals_enabled" value="1" <?= $s('rituals_enabled')==='1'?'checked':'' ?>>
              </label>
            </div>

            <!-- Dashboard -->
            <div style="display:flex;align-items:center;justify-content:space-between">
              <div>
                <div style="font-weight:500">Growth Dashboard</div>
                <div class="text-muted text-sm">Enable emotional timeline and mood trend charts at /timeline.php.</div>
              </div>
              <label class="switch">
                <input type="checkbox" name="growth_dashboard_enabled" value="1" <?= $s('growth_dashboard_enabled')==='1'?'checked':'' ?>>
              </label>
            </div>

            <!-- Snapshots -->
            <div style="display:flex;align-items:center;justify-content:space-between">
              <div>
                <div style="font-weight:500">Nightly Analytics Snapshots</div>
                <div class="text-muted text-sm">Compute daily growth scores and mood trends for all active users.</div>
              </div>
              <label class="switch">
                <input type="checkbox" name="analytics_snapshots_enabled" value="1" <?= $s('analytics_snapshots_enabled')==='1'?'checked':'' ?>>
              </label>
            </div>

            <!-- Reminders -->
            <div style="display:flex;align-items:center;justify-content:space-between">
              <div>
                <div style="font-weight:500">Adaptive Reminder Engine</div>
                <div class="text-muted text-sm">Send intelligent, emotionally-aware reminders based on user patterns.</div>
              </div>
              <label class="switch">
                <input type="checkbox" name="reminder_engine_enabled" value="1" <?= $s('reminder_engine_enabled')==='1'?'checked':'' ?>>
              </label>
            </div>
          </div>
          
          <div style="margin-top:20px;padding:14px;background:rgba(184,149,106,0.06);border:1px solid rgba(184,149,106,0.15);border-radius:10px;font-size:13px;color:var(--muted)">
            💡 Requires SMTP configured and cron running (Jobs 7, 8 & 9 in api/cron.php).
          </div>
        </div>
      </div>

      <?php elseif ($tab === 'seo'): ?>
      <div style="display:flex;flex-direction:column;gap:16px;max-width:700px">
        <div class="card">
          <div class="card-header"><div class="card-title">SEO & Metadata</div></div>
          <div class="form-grid">
            <div class="form-group" style="grid-column:1/-1">
              <label>Default Meta Title</label>
              <input class="form-control" name="meta_title_default" value="<?= $s('meta_title_default','Solen — Your Personal AI Wellness Coach') ?>"/>
            </div>
            <div class="form-group" style="grid-column:1/-1">
              <label>Default Meta Description</label>
              <textarea class="form-control" name="meta_desc_default" rows="3"><?= $s('meta_desc_default','A private, safe space to reflect, grow, and navigate life with an AI companion that remembers you.') ?></textarea>
            </div>
            <div class="form-group">
              <label>OG Image URL</label>
              <input class="form-control" name="og_image" value="<?= $s('og_image','/assets/og-image.png') ?>"/>
            </div>
            <div class="form-group">
              <label>Twitter Handle</label>
              <input class="form-control" name="twitter_handle" value="<?= $s('twitter_handle','@getsolen') ?>"/>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><div class="card-title">Indexing</div></div>
          <div style="display:flex;flex-direction:column;gap:12px">
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
              <input type="checkbox" name="sitemap_enabled" value="1" <?= $s('sitemap_enabled')==='1'?'checked':'' ?>>
              Generate Dynamic Sitemap (/sitemap.xml)
            </label>
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
              <input type="checkbox" name="robots_index" value="1" <?= $s('robots_index')==='1'?'checked':'' ?>>
              Allow Search Engines to Index (robots.txt)
            </label>
          </div>
        </div>
      </div>
      </div>

      <?php elseif ($tab === 'danger'): ?>
      <div style="max-width:600px">
        <div class="card" style="border-color:rgba(239,68,68,0.3)">
          <div class="card-header" style="border-bottom-color:rgba(239,68,68,0.2)">
            <div class="card-title" style="color:#fca5a5">⚠ Danger Zone</div>
          </div>
          <div style="display:flex;flex-direction:column;gap:20px">
            <div style="display:flex;align-items:center;justify-content:space-between;padding:16px;background:rgba(239,68,68,0.05);border:1px solid rgba(239,68,68,0.15);border-radius:10px">
              <div>
                <div style="font-weight:500;margin-bottom:4px">Purge All Mood Data</div>
                <div class="text-muted text-sm">Delete all mood logs across all users. Irreversible.</div>
              </div>
              <a href="/admin/settings.php?do=purge_moods&csrf=<?= csrf_token() ?>"
                 onclick="return confirm('Delete ALL mood data? This cannot be undone.')"
                 class="btn btn-danger btn-sm">Purge Moods</a>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:16px;background:rgba(239,68,68,0.05);border:1px solid rgba(239,68,68,0.15);border-radius:10px">
              <div>
                <div style="font-weight:500;margin-bottom:4px">Purge All Coach Memory</div>
                <div class="text-muted text-sm">Delete all session memories across all users.</div>
              </div>
              <a href="/admin/settings.php?do=purge_memory&csrf=<?= csrf_token() ?>"
                 onclick="return confirm('Delete ALL coach memory? This cannot be undone.')"
                 class="btn btn-danger btn-sm">Purge Memory</a>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:16px;background:rgba(239,68,68,0.05);border:1px solid rgba(239,68,68,0.15);border-radius:10px">
              <div>
                <div style="font-weight:500;margin-bottom:4px">Export Full Database</div>
                <div class="text-muted text-sm">Download a JSON export of all non-sensitive data.</div>
              </div>
              <a href="/admin/settings.php?do=export&csrf=<?= csrf_token() ?>" class="btn btn-ghost btn-sm">Export JSON</a>
            </div>
          </div>
        </div>
        <div style="margin-top:14px;padding:14px;background:var(--card);border-radius:10px;font-size:13px;color:var(--muted)">
          Database path: <code style="color:var(--accent)"><?= h(DB_PATH) ?></code><br>
          Size: <strong style="color:var(--text)"><?= file_exists(DB_PATH) ? round(filesize(DB_PATH)/1024, 1).'KB' : 'Not created yet' ?></strong>
        </div>
      </div>
      <?php endif ?>

      <?php if ($tab !== 'danger'): ?>
      <div style="margin-top:20px">
        <button class="btn btn-primary">Save <?= ucfirst($tab) ?> Settings</button>
      </div>
      <?php endif ?>
    </form>
  </div>
</div>
</body></html>
<?php
// Handle danger zone GET actions
$do = $_GET['do'] ?? '';
if ($do && verify_csrf($_GET['csrf'] ?? '')) {
    if ($do === 'purge_moods')  { db_run("DELETE FROM mood_logs"); flash('success','All mood logs deleted.'); }
    if ($do === 'purge_memory') { db_run("DELETE FROM coach_memory"); flash('success','All coach memory deleted.'); }
    if ($do === 'export') {
        $data = [
            'exported_at' => date('c'),
            'users'       => db_query("SELECT id,name,email,plan,role,created_at FROM users"),
            'posts'       => db_query("SELECT id,title,slug,category,status,views,published_at FROM blog_posts"),
            'subscriptions' => db_query("SELECT id,user_id,plan,status,amount,billing_cycle,started_at FROM subscriptions"),
        ];
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="solen-export-'.date('Y-m-d').'.json"');
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
    redirect('/admin/settings.php?tab=danger');
}
