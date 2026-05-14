<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/seo.php';

$site   = get_setting('site_name','Solen');
$headline = get_setting('pricing_headline','Start free. Stay because it works.');
$subtext  = get_setting('pricing_subtext','Seven days free. No card. No commitment.');
$trial    = get_setting('trial_days','7');

$plans = [
  'free' => [
    'name'  => 'Free Trial',
    'desc'  => "Full experience, 7 days only",
    'price' => 'Free',
    'note'  => 'No credit card required',
    'badge' => false,
    'cta'   => is_logged_in() ? 'Open App' : 'Start free trial',
    'href'  => is_logged_in() ? '/app.php' : '/register.php',
    'style' => 'outline',
    'features' => [
      [true,  "Full access for 7 days"],
      [true,  '20 AI requests per day'],
      [true,  'Basic coach setup'],
      [true,  'Daily check-ins'],
      [false, 'Persistent memory'],
      [false, 'Growth programs'],
      [false, 'Streak milestones'],
      [false, 'Live voice sessions'],
      [false, 'Family sharing'],
      [false, 'Recovery rituals'],
    ],
  ],
  'plus' => [
    'name'  => 'Solen Plus',
    'desc'  => 'For casual daily reflection',
    'price' => '$' . get_setting('price_plus_monthly','5') . '.99',
    'note'  => 'Best for beginners',
    'badge' => false,
    'cta'   => 'Get Solen Plus',
    'href'  => is_logged_in() ? '/upgrade.php?plan=plus' : '/register.php?plan=plus',
    'style' => 'gold',
    'features' => [
      [true,  '50 AI requests per day'],
      [true,  'Persistent memory (basic)'],
      [true,  'Full mood history'],
      [true,  'Daily rituals'],
      [false, 'Growth programs'],
      [false, 'Streak milestones'],
      [false, 'Live voice sessions'],
      [false, 'Family sharing'],
      [false, 'Recovery rituals'],
    ],
  ],
  'pro' => [
    'name'  => 'Solen Pro',
    'desc'  => 'For deep wellness work',
    'price' => '$' . get_setting('price_pro_monthly','12') . '.99',
    'note'  => 'Most popular choice',
    'badge' => 'Most Popular',
    'cta'   => 'Get Solen Pro',
    'href'  => is_logged_in() ? '/upgrade.php?plan=pro' : '/register.php?plan=pro',
    'style' => 'gold',
    'features' => [
      [true,  '200 AI requests per day'],
      [true,  'Persistent memory (advanced)'],
      [true,  'Session summaries & insights'],
      [true,  'All growth programs (Learn)'],
      [true,  'Streak tracking & milestones'],
      [false, 'Live voice sessions'],
      [false, 'Family sharing'],
      [false, 'Recovery rituals'],
    ],
  ],
  'premium' => [
    'name'  => 'Solen Premium',
    'desc'  => 'The complete experience',
    'price' => '$' . get_setting('price_premium_monthly','24') . '.99',
    'note'  => 'For total wellness mastery',
    'badge' => false,
    'cta'   => 'Get Solen Premium',
    'href'  => is_logged_in() ? '/upgrade.php?plan=premium' : '/register.php?plan=premium',
    'style' => 'gold',
    'features' => [
      [true,  '500 AI requests per day'],
      [true,  'Persistent memory (advanced)'],
      [true,  'All growth programs (Learn)'],
      [true,  'Streak tracking & milestones'],
      [true,  'Live voice sessions'],
      [true,  'Family sharing (up to 4)'],
      [true,  'Addiction recovery rituals'],
      [true,  'Priority AI speed'],
    ],
  ],
];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Pricing — <?= h($site) ?></title>
<?php seo_head([
  'title'     => 'Pricing — ' . get_setting('site_name','Solen') . ' | AI Wellness Coach',
  'desc'      => 'Solen pricing plans starting free. Pro from $12.99/mo. Unlimited AI wellness coaching, mood tracking & personal growth programs. No credit card for trial.',
  'og_type'   => 'website',
]); ?>
<meta name="description"  content="Solen pricing plans. Start with a <?= $trial ?>-day free trial — no credit card. Upgrade to Pro or Premium for persistent memory, voice sessions, and structured growth programs."/>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;1,400&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
<style>
*{box-sizing:border-box;margin:0;padding:0} body{background:#080810;color:#f0ede8;font-family:'DM Sans',sans-serif}
:root{--accent:#c5a572;--gold:#e8c97a;--border:rgba(255,255,255,0.07)}
nav{padding:18px 0;border-bottom:1px solid var(--border);background:rgba(8,8,16,0.9);position:sticky;top:0;z-index:10}
.nav-in{max-width:1100px;margin:0 auto;padding:0 24px;display:flex;align-items:center;justify-content:space-between}
.logo{font-family:'Cormorant Garamond',serif;font-size:24px;color:var(--accent);text-decoration:none}.logo span{color:#f0ede8}
.nav-links{display:flex;gap:24px;align-items:center}.nav-links a{font-size:13px;color:rgba(240,237,232,0.5)}
.nav-links a:hover{color:#f0ede8}
.btn-cta{background:var(--accent);color:#1a1008;padding:9px 20px;border-radius:50px;font-size:13px;font-weight:500}
.wrap{max-width:1100px;margin:0 auto;padding:0 24px}
.hero{padding:80px 0 60px;text-align:center}
.hero-label{font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:var(--accent);margin-bottom:16px;display:block}
.hero h1{font-family:'Cormorant Garamond',serif;font-size:clamp(36px,5vw,64px);font-weight:300;margin-bottom:16px}
.hero h1 em{font-style:italic;color:var(--accent)}
.hero p{color:rgba(240,237,232,0.5);font-size:17px;max-width:500px;margin:0 auto}
.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px;padding-bottom:80px;align-items:start}
.plan{background:#0e0e1a;border:1px solid var(--border);border-radius:24px;padding:34px 28px;position:relative;transition:all 0.3s}
.plan:hover{transform:translateY(-4px)}
.plan.featured{background:#131325;border-color:rgba(197,165,114,0.35);box-shadow:0 0 60px rgba(197,165,114,0.08)}
.badge{position:absolute;top:-14px;left:50%;transform:translateX(-50%);background:var(--accent);color:#1a1008;padding:5px 18px;border-radius:50px;font-size:11px;font-weight:500;letter-spacing:0.08em;text-transform:uppercase;white-space:nowrap}
.plan-name{font-family:'Cormorant Garamond',serif;font-size:26px;font-weight:400;margin-bottom:5px}
.plan-desc{font-size:13px;color:rgba(240,237,232,0.5);margin-bottom:24px}
.price{font-family:'Cormorant Garamond',serif;font-size:52px;font-weight:300;line-height:1}
.price.free{color:var(--accent);font-size:40px}
.price-note{font-size:12px;color:rgba(240,237,232,0.4);margin:6px 0 24px;min-height:18px}
.divider{height:1px;background:rgba(255,255,255,0.07);margin-bottom:24px}
.features{list-style:none;display:flex;flex-direction:column;gap:13px;margin-bottom:28px}
.features li{display:flex;align-items:flex-start;gap:10px;font-size:13px}
.features li.on{color:#f0ede8}.features li.off{color:rgba(240,237,232,0.35)}
.features li.on::before{content:'✓';color:var(--accent);flex-shrink:0;margin-top:1px}
.features li.off::before{content:'–';color:rgba(255,255,255,0.2);flex-shrink:0}
.plan-btn{width:100%;padding:13px;border-radius:50px;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:500;cursor:pointer;border:none;transition:all 0.2s;text-align:center;display:block;text-decoration:none}
.plan-btn.gold{background:var(--accent);color:#1a1008}.plan-btn.gold:hover{background:var(--gold)}
.plan-btn.outline{background:transparent;border:1px solid rgba(255,255,255,0.15);color:#f0ede8}.plan-btn.outline:hover{border-color:var(--accent);color:var(--accent)}
.guarantee{text-align:center;padding:0 0 60px;font-size:14px;color:rgba(240,237,232,0.4);display:flex;align-items:center;justify-content:center;gap:8px}
.guarantee::before{content:'🔒'}
.faq{padding:60px 0;border-top:1px solid var(--border)}
.faq h2{font-family:'Cormorant Garamond',serif;font-size:38px;font-weight:300;text-align:center;margin-bottom:40px}
.faq-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:860px;margin:0 auto}
.faq-item{background:#0e0e1a;border:1px solid var(--border);border-radius:14px;padding:22px}
.faq-q{font-family:'Cormorant Garamond',serif;font-size:18px;font-weight:400;margin-bottom:10px}
.faq-a{font-size:13px;color:rgba(240,237,232,0.55);line-height:1.75}
footer{border-top:1px solid var(--border);padding:32px 0;text-align:center;font-size:13px;color:rgba(240,237,232,0.3)}
footer a{color:var(--accent)}
@media(max-width:860px){.grid,.faq-grid{grid-template-columns:1fr}.nav-links{display:none}}
</style>
</head>
<body>
<nav>
  <div class="nav-in">
    <a href="/" class="logo">Sol<span>en</span></a>
    <div class="nav-links">
      <a href="/">Home</a><a href="/blog.php">Blog</a>
    </div>
    <a href="<?= is_logged_in() ? '/app.php' : '/register.php' ?>" class="btn-cta"><?= is_logged_in() ? 'Open App' : 'Start Free' ?></a>
  </div>
</nav>

<section class="hero">
  <div class="wrap">
    <?php if (isset($_GET['expired'])): ?>
      <div style="background:rgba(248,113,113,0.1);border:1px solid rgba(248,113,113,0.2);padding:14px 20px;border-radius:12px;color:#f87171;font-size:14px;margin-bottom:32px;display:inline-block;text-align:left;">
        <strong style="display:block;margin-bottom:4px">Trial Expired</strong>
        Your 7-day free trial has ended. Upgrade to a plan below to continue your wellness journey.
      </div>
    <?php endif ?>
    <span class="hero-label">Simple, honest pricing</span>
    <h1><?= h($headline) ?></h1>
    <p><?= h($subtext) ?></p>
  </div>
</section>

<div class="wrap">
  <div class="grid">
    <?php foreach ($plans as $key => $plan): ?>
    <div class="plan <?= $plan['badge'] ? 'featured' : '' ?>">
      <?php if ($plan['badge']): ?><div class="badge"><?= h($plan['badge']) ?></div><?php endif ?>
      <div class="plan-name"><?= h($plan['name']) ?></div>
      <div class="plan-desc"><?= h($plan['desc']) ?></div>
      <div class="price <?= $key==='free'?'free':'' ?>"><?= h($plan['price']) ?></div>
      <?php if ($key !== 'free'): ?><div style="font-size:12px;color:rgba(240,237,232,0.45);margin-top:4px">/month</div><?php endif ?>
      <div class="price-note"><?= h($plan['note']) ?></div>
      <div class="divider"></div>
      <ul class="features">
        <?php foreach ($plan['features'] as [$on, $label]): ?>
          <li class="<?= $on?'on':'off' ?>"><?= h($label) ?></li>
        <?php endforeach ?>
      </ul>
      <a href="<?= h($plan['href']) ?>" class="plan-btn <?= $plan['style'] ?>"><?= h($plan['cta']) ?></a>
    </div>
    <?php endforeach ?>
  </div>

  <div class="guarantee">30-day money-back guarantee on all paid plans. No questions asked.</div>

  <div class="faq">
    <h2>Common questions</h2>
    <div class="faq-grid">
      <div class="faq-item">
        <div class="faq-q">Is Solen a replacement for therapy?</div>
        <div class="faq-a">No. Solen is a wellness coaching companion, not a licensed therapist. It works alongside therapy, or as a daily check-in tool. For clinical issues, please see a professional.</div>
      </div>
      <div class="faq-item">
        <div class="faq-q">How does the memory work?</div>
        <div class="faq-a">After each session, Solen silently extracts themes and emotional patterns and stores them privately. Next time, your coach references what you've shared before — so every conversation builds on the last.</div>
      </div>
      <div class="faq-item">
        <div class="faq-q">What happens after my free trial?</div>
        <div class="faq-a">You'll choose a plan that suits you. If you don't upgrade, your account moves to read-only. Your data is never deleted.</div>
      </div>
      <div class="faq-item">
        <div class="faq-q">Is my data private?</div>
        <div class="faq-a">Your conversations are encrypted and never sold or shared. You can export or delete all your data at any time from settings.</div>
      </div>
      <div class="faq-item">
        <div class="faq-q">Can I cancel anytime?</div>
        <div class="faq-a">Yes. Cancel in one tap from your account settings. If you're within 30 days of a paid charge and it didn't work for you, we'll refund it — no questions.</div>
      </div>
      <div class="faq-item">
        <div class="faq-q">Does voice work on mobile?</div>
        <div class="faq-a">Yes — Chrome and Safari on iOS and Android. You'll be prompted to allow mic access the first time. Optimised for hands-free use.</div>
      </div>
    </div>
  </div>
</div>

<footer>
  <div class="wrap">
    © 2026 <?= h($site) ?> Inc. &nbsp;·&nbsp; <a href="/">Home</a> &nbsp;·&nbsp; <a href="/blog.php">Blog</a> &nbsp;·&nbsp; <a href="/login.php">Sign In</a>
    <br style="margin-top:8px"/><span style="font-size:11px">Solen is not a medical service. In crisis? Call 988.</span>
  </div>
</footer>
</body>
</html>
