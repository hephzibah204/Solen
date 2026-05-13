<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$gateway = $_GET['gateway'] ?? 'stripe';
$user    = current_user();

// ── STRIPE ────────────────────────────────────────────────────────────────
// Verify the Checkout Session and activate the plan. activate_plan() is
// idempotent (deduplicates on session_id), so it's safe if the webhook
// already fired — it will simply no-op.
if ($gateway === 'stripe' && isset($_GET['session_id'])) {
    $sessionId = $_GET['session_id'];
    $sk = get_setting('stripe_sk') ?: (defined('STRIPE_SK') ? STRIPE_SK : '');
    if ($sk) {
        $ch = curl_init("https://api.stripe.com/v1/checkout/sessions/" . urlencode($sessionId));
        curl_setopt_array($ch, [
            CURLOPT_USERPWD        => $sk . ':',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $res  = curl_exec($ch);
        $data = json_decode($res, true);
        curl_close($ch);

        if (($data['payment_status'] ?? '') === 'paid' || ($data['status'] ?? '') === 'complete') {
            $meta    = $data['metadata'] ?? [];
            $billing = $meta['billing'] ?? 'monthly';
            $plan    = $meta['plan']    ?? 'pro';
            $amount  = ($data['amount_total'] ?? 0) / 100;
            $custId  = $data['customer'] ?? null;

            // Persist stripe_customer_id for reliable future webhook lookups.
            if ($custId) {
                get_db()->prepare("UPDATE users SET stripe_customer_id=? WHERE id=?")
                        ->execute([$custId, $user['id']]);
            }

            // activate_plan() checks for the session_id ref first — safe to call
            // even if the webhook already ran.
            activate_plan((int)$user['id'], $plan, $billing, $amount, 'stripe', $sessionId);
        }
    }
}

// ── FLUTTERWAVE ───────────────────────────────────────────────────────────
if ($gateway === 'flutterwave' && isset($_GET['transaction_id'])) {
    $txId = (int)$_GET['transaction_id'];
    $sk   = get_setting('flutterwave_sk') ?: (defined('FLUTTERWAVE_SK') ? FLUTTERWAVE_SK : '');
    $ch   = curl_init("https://api.flutterwave.com/v3/transactions/{$txId}/verify");
    curl_setopt_array($ch, [CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$sk], CURLOPT_RETURNTRANSFER=>true]);
    $res  = json_decode(curl_exec($ch), true);
    curl_close($ch);
    if (($res['data']['status'] ?? '') === 'successful') {
        $meta = $res['data']['meta'] ?? [];
        activate_plan((int)$user['id'], $meta['plan'] ?? 'pro', $meta['billing'] ?? 'monthly', $res['data']['amount'], 'flutterwave', (string)$txId);
    }
}

// ── PAYSTACK ──────────────────────────────────────────────────────────────
if ($gateway === 'paystack' && isset($_GET['reference'])) {
    $ref = $_GET['reference'];
    $sk  = get_setting('paystack_sk') ?: (defined('PAYSTACK_SK') ? PAYSTACK_SK : '');
    $ch  = curl_init("https://api.paystack.co/transaction/verify/{$ref}");
    curl_setopt_array($ch, [CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$sk], CURLOPT_RETURNTRANSFER=>true]);
    $res = json_decode(curl_exec($ch), true);
    curl_close($ch);
    if (($res['data']['status'] ?? '') === 'success') {
        $meta = $res['data']['metadata'] ?? [];
        activate_plan((int)$user['id'], $meta['plan'] ?? 'pro', $meta['billing'] ?? 'monthly', $res['data']['amount'] / 100, 'paystack', $ref);
    }
}

$site = get_setting('site_name','Solen');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Payment Successful — <?= h($site) ?></title>
<meta name="robots" content="noindex"/>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500&display=swap" rel="stylesheet"/>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#07070f;color:#f2ede8;font-family:'Outfit',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;text-align:center;padding:24px}
.icon{font-size:56px;margin-bottom:16px}
h1{font-size:28px;font-weight:500;margin-bottom:10px}
p{color:rgba(242,237,232,.55);font-size:15px;margin-bottom:28px}
a{display:inline-block;padding:13px 32px;background:#b8956a;color:#1a1206;border-radius:10px;text-decoration:none;font-weight:600;font-size:15px}
</style>
</head>
<body>
<div>
  <div class="icon">🎉</div>
  <h1>You're all set!</h1>
  <p>Your subscription is now active. Welcome to your upgraded Solen experience.</p>
  <a href="/app.php">Open Solen →</a>
</div>
</body>
</html>
