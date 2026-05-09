<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$user    = current_user();
$plan    = $_GET['plan']    ?? 'pro';
$billing = $_GET['billing'] ?? 'monthly';

// Plans & prices
$planMeta = [
    'plus'    => ['label' => 'Solen Plus',    'desc' => 'Basic memory, mood history, 1 growth program'],
    'pro'     => ['label' => 'Solen Pro',     'desc' => 'Persistent memory, full mood history, all programs'],
    'premium' => ['label' => 'Solen Premium', 'desc' => 'Everything in Pro + voice, priority speed, family sharing'],
];
$monthlyPrice = (float)(get_setting("price_{$plan}_monthly") ?: PLANS[$plan]['price']);
$yearlyPrice  = (float)(get_setting("price_{$plan}_yearly")  ?: PLANS[$plan]['yearly']);
$displayPrice = $billing === 'yearly' ? $yearlyPrice : $monthlyPrice;

// Which gateways are enabled
$gateways = [];
if (get_setting('stripe_sk') ?: STRIPE_SK)         $gateways[] = ['id'=>'stripe',       'label'=>'Credit / Debit Card', 'icon'=>'💳'];
if (get_setting('paystack_sk') ?: PAYSTACK_SK)      $gateways[] = ['id'=>'paystack',     'label'=>'Paystack',            'icon'=>'🟢'];
if (get_setting('flutterwave_sk') ?: FLUTTERWAVE_SK) $gateways[] = ['id'=>'flutterwave', 'label'=>'Flutterwave',         'icon'=>'🦋'];
if (empty($gateways)) $gateways[] = ['id'=>'stripe','label'=>'Credit / Debit Card','icon'=>'💳'];

$site = get_setting('site_name','Solen');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Upgrade to <?= h($planMeta[$plan]['label'] ?? 'Pro') ?> — <?= h($site) ?></title>
<meta name="robots" content="noindex"/>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#07070f;--surface:#0d0d1e;--border:rgba(255,255,255,0.08);--accent:#b8956a;--text:#f2ede8;--muted:rgba(242,237,232,0.45)}
body{background:var(--bg);color:var(--text);font-family:'Outfit',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
.card{background:var(--surface);border:1px solid var(--border);border-radius:18px;padding:40px;max-width:480px;width:100%}
h1{font-size:24px;font-weight:500;margin-bottom:6px}
.sub{color:var(--muted);font-size:14px;margin-bottom:28px}
.price-row{display:flex;align-items:baseline;gap:6px;margin-bottom:24px}
.price{font-size:38px;font-weight:300}
.period{color:var(--muted);font-size:14px}
.toggle{display:flex;gap:8px;margin-bottom:24px;background:rgba(255,255,255,0.04);border-radius:8px;padding:4px}
.toggle button{flex:1;padding:8px;border:none;border-radius:6px;cursor:pointer;font-size:13px;font-family:'Outfit',sans-serif;transition:all .2s}
.toggle button.active{background:var(--accent);color:#1a1206;font-weight:500}
.toggle button:not(.active){background:transparent;color:var(--muted)}
.gw-list{display:flex;flex-direction:column;gap:10px;margin-bottom:24px}
.gw-option{display:flex;align-items:center;gap:12px;padding:14px 16px;border:1px solid var(--border);border-radius:10px;cursor:pointer;transition:all .2s}
.gw-option.selected{border-color:var(--accent);background:rgba(184,149,106,0.07)}
.gw-option input{accent-color:var(--accent)}
.btn{width:100%;padding:14px;background:var(--accent);color:#1a1206;border:none;border-radius:10px;font-size:15px;font-weight:600;cursor:pointer;font-family:'Outfit',sans-serif;transition:opacity .2s}
.btn:hover{opacity:.9}
.btn:disabled{opacity:.5;cursor:wait}
.secure{text-align:center;color:var(--muted);font-size:12px;margin-top:12px}
.err{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#fca5a5;padding:12px;border-radius:8px;font-size:13px;margin-bottom:14px;display:none}
</style>
</head>
<body>
<div class="card">
  <h1>Upgrade to <?= h($planMeta[$plan]['label'] ?? ucfirst($plan)) ?></h1>
  <p class="sub"><?= h($planMeta[$plan]['desc'] ?? '') ?></p>

  <div class="toggle">
    <button id="btn-monthly" onclick="setBilling('monthly')" class="<?= $billing!=='yearly'?'active':'' ?>">Monthly</button>
    <button id="btn-yearly"  onclick="setBilling('yearly')"  class="<?= $billing==='yearly'?'active':'' ?>">Yearly <span style="color:rgba(255,255,255,.4);font-size:11px">save 36%</span></button>
  </div>

  <div class="price-row">
    <span class="price" id="price-display">$<?= number_format($displayPrice, 2) ?></span>
    <span class="period" id="period-display">/<?= $billing === 'yearly' ? 'year' : 'month' ?></span>
  </div>

  <div class="gw-list">
    <?php foreach ($gateways as $i => $gw): ?>
    <label class="gw-option <?= $i===0?'selected':'' ?>" onclick="selectGw(this)">
      <input type="radio" name="gateway" value="<?= $gw['id'] ?>" <?= $i===0?'checked':'' ?>/>
      <span style="font-size:20px"><?= $gw['icon'] ?></span>
      <span><?= h($gw['label']) ?></span>
    </label>
    <?php endforeach ?>
  </div>

  <div class="err" id="err-box"></div>
  <button class="btn" id="pay-btn" onclick="startPayment()">Continue to Payment</button>
  <p class="secure">🔒 Secure checkout · Cancel anytime</p>
</div>

<script>
const MONTHLY = <?= json_encode($monthlyPrice) ?>;
const YEARLY  = <?= json_encode($yearlyPrice) ?>;
let billing   = <?= json_encode($billing) ?>;

function setBilling(b) {
    billing = b;
    document.getElementById('price-display').textContent = '$' + (b==='yearly'?YEARLY:MONTHLY).toFixed(2);
    document.getElementById('period-display').textContent = '/' + (b==='yearly'?'year':'month');
    document.getElementById('btn-monthly').classList.toggle('active', b!=='yearly');
    document.getElementById('btn-yearly').classList.toggle('active',  b==='yearly');
}

function selectGw(el) {
    document.querySelectorAll('.gw-option').forEach(o => o.classList.remove('selected'));
    el.classList.add('selected');
}

async function startPayment() {
    const gw  = document.querySelector('input[name=gateway]:checked')?.value || 'stripe';
    const btn = document.getElementById('pay-btn');
    const err = document.getElementById('err-box');

    // TEST MODE SIMULATION (Phase 8)
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('simulate') === '1') {
        btn.disabled = true;
        btn.textContent = 'Simulating Payment…';
        setTimeout(async () => {
            try {
                const res = await fetch('/api/payment.php', {
                    method: 'POST',
                    headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({action:'simulate_success', plan:<?= json_encode($plan) ?>, billing})
                });
                const data = await res.json();
                if (data.status === 'success') window.location.href = '/upgrade-success.php?status=success';
                else throw new Error(data.error || 'Simulation failed');
            } catch(e) {
                err.textContent = "Simulation Error: " + e.message;
                err.style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Continue to Payment';
            }
        }, 1500);
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Processing…';
    err.style.display = 'none';

    try {
        const res  = await fetch('/api/payment.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({action:'initiate', gateway:gw, plan:<?= json_encode($plan) ?>, billing})
        });
        const data = await res.json();
        if (data.url) { window.location.href = data.url; }
        else { throw new Error(data.error || 'Payment failed'); }
    } catch(e) {
        err.textContent  = e.message;
        err.style.display = 'block';
        btn.disabled     = false;
        btn.textContent  = 'Continue to Payment';
    }
}
</script>
</body>
</html>
