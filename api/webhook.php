<?php
/**
 * /api/webhook.php — Payment webhook handlers
 * Stripe, Flutterwave, Paystack
 */
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$gateway = $_GET['gateway'] ?? 'stripe';
$raw     = file_get_contents('php://input');

switch ($gateway) {
    case 'stripe':      handle_stripe_webhook($raw);      break;
    case 'flutterwave': handle_flutterwave_webhook($raw);  break;
    case 'paystack':    handle_paystack_webhook($raw);     break;
    default: http_response_code(400); die('Unknown gateway');
}

// ── STRIPE ────────────────────────────────────────────────────────────────
function handle_stripe_webhook(string $raw): void {
    $secret = get_setting('stripe_webhook_secret') ?: (defined('STRIPE_WEBHOOK_SECRET') ? STRIPE_WEBHOOK_SECRET : '');
    $sig    = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

    if ($secret) {
        $parts = [];
        foreach (explode(',', $sig) as $part) {
            [$k, $v] = explode('=', $part, 2);
            $parts[$k][] = $v;
        }
        $timestamp = $parts['t'][0] ?? 0;
        $expected  = hash_hmac('sha256', "{$timestamp}.{$raw}", $secret);
        if (!in_array($expected, $parts['v1'] ?? [], true)) {
            http_response_code(400); die('Bad signature');
        }
    }

    $event = json_decode($raw, true);
    $type  = $event['type'] ?? '';

    // ── checkout.session.completed ────────────────────────────────────────
    if ($type === 'checkout.session.completed') {
        $session   = $event['data']['object'];
        $userId    = (int)($session['metadata']['user_id'] ?? 0);
        $plan      = $session['metadata']['plan']    ?? 'pro';
        $billing   = $session['metadata']['billing'] ?? 'monthly';
        $amount    = ($session['amount_total'] ?? 0) / 100;
        $custId    = $session['customer'] ?? null;  // Stripe customer_id
        $sessionId = $session['id'] ?? '';

        if ($userId) {
            // Persist stripe_customer_id on the user for future webhook lookups.
            if ($custId) {
                get_db()->prepare("UPDATE users SET stripe_customer_id=? WHERE id=?")
                        ->execute([$custId, $userId]);
            }
            activate_plan($userId, $plan, $billing, $amount, 'stripe', $sessionId);
        }
    }

    // ── customer.subscription.deleted ─────────────────────────────────────
    if ($type === 'customer.subscription.deleted') {
        $sub    = $event['data']['object'];
        $custId = $sub['customer'] ?? null;
        if ($custId) {
            // Look up by the proper stripe_customer_id column (reliable).
            $row = db_one("SELECT id FROM users WHERE stripe_customer_id=?", [$custId]);
            if ($row) {
                $uid = (int)$row['id'];
                get_db()->prepare("UPDATE users SET plan='free' WHERE id=?")->execute([$uid]);
                get_db()->prepare(
                    "UPDATE subscriptions SET status='cancelled', cancelled_at=datetime('now')
                      WHERE user_id=? AND status='active'"
                )->execute([$uid]);
            }
        }
    }

    // ── invoice.payment_failed ────────────────────────────────────────────
    // Fires when a renewal charge fails. We do NOT immediately downgrade —
    // Stripe will retry several times. We mark the subscription as 'past_due'
    // so the admin dashboard can surface it, and send a warning email if
    // the email system is configured.
    if ($type === 'invoice.payment_failed') {
        $invoice = $event['data']['object'];
        $custId  = $invoice['customer'] ?? null;
        if ($custId) {
            $row = db_one("SELECT id, email, name FROM users WHERE stripe_customer_id=?", [$custId]);
            if ($row) {
                $uid = (int)$row['id'];
                // Mark active subscription as past_due (not cancelled).
                get_db()->prepare(
                    "UPDATE subscriptions SET status='past_due'
                      WHERE user_id=? AND status='active'"
                )->execute([$uid]);

                // Best-effort warning email.
                $site    = get_setting('site_name', 'Solen');
                $siteUrl = rtrim(get_setting('canonical_url') ?: SITE_URL, '/');
                $first   = explode(' ', trim($row['name']))[0];
                $content = <<<HTML
<h1 style="margin:0 0 12px;font-family:'Georgia',serif;font-size:24px;font-weight:400;color:#f0ede8;">Payment issue, {$first}</h1>
<p style="margin:0 0 20px;font-size:15px;color:#f0ede8;line-height:1.75;">
  We weren't able to charge your card for your {$site} subscription renewal.
  Your account is still active while we retry — but please update your payment method to avoid losing access.
</p>
<table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:20px;">
  <tr><td align="center">
    <a href="https://billing.stripe.com/p/login/test_placeholder" style="display:inline-block;background:#c5a572;color:#1a1008;padding:13px 32px;border-radius:50px;text-decoration:none;font-family:Arial,sans-serif;font-size:14px;font-weight:600;">Update Payment Method →</a>
  </td></tr>
</table>
<p style="margin:0;font-size:12px;color:rgba(240,237,232,0.35);line-height:1.7;">
  If you have questions, just reply to this email. We'll never delete your data.
</p>
HTML;
                $html = email_layout($content, "Action needed — we couldn't process your renewal payment.");
                send_email($row['email'], "Action needed: payment failed for {$site}", $html);
            }
        }
    }

    http_response_code(200);
    echo 'ok';
}

// ── FLUTTERWAVE ───────────────────────────────────────────────────────────
function handle_flutterwave_webhook(string $raw): void {
    $secret = get_setting('flutterwave_webhook_secret') ?? '';
    $hash   = $_SERVER['HTTP_VERIF_HASH'] ?? '';
    if ($secret && !hash_equals($secret, $hash)) { http_response_code(401); die('Bad hash'); }

    $data = json_decode($raw, true);
    if (($data['event'] ?? '') === 'charge.completed' && ($data['data']['status'] ?? '') === 'successful') {
        $txData  = $data['data'];
        $meta    = $txData['meta'] ?? [];
        $userId  = $meta['user_id'] ?? null;
        $plan    = $meta['plan']    ?? 'pro';
        $billing = $meta['billing'] ?? 'monthly';
        if ($userId) activate_plan((int)$userId, $plan, $billing, $txData['amount'], 'flutterwave', (string)$txData['id']);
    }
    http_response_code(200);
    echo 'ok';
}

// ── PAYSTACK ─────────────────────────────────────────────────────────────
function handle_paystack_webhook(string $raw): void {
    $sk   = get_setting('paystack_sk') ?: PAYSTACK_SK;
    $sig  = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
    if ($sk && hash_hmac('sha512', $raw, $sk) !== $sig) { http_response_code(401); die('Bad sig'); }

    $data = json_decode($raw, true);
    if (($data['event'] ?? '') === 'charge.success') {
        $txData  = $data['data'];
        $meta    = $txData['metadata'] ?? [];
        $userId  = $meta['user_id'] ?? null;
        $plan    = $meta['plan']    ?? 'pro';
        if ($userId) activate_plan((int)$userId, $plan, 'monthly', $txData['amount']/100, 'paystack', $txData['reference']);
    }
    http_response_code(200);
    echo 'ok';
}

// ── SHARED ────────────────────────────────────────────────────────────────
function activate_plan(int $userId, string $plan, string $billing, float $amount, string $gateway, string $ref): void {
    // Idempotency: if we've already processed this exact transaction reference,
    // skip — prevents duplicate rows when both the webhook and upgrade-success
    // page fire for the same payment.
    $existing = db_one(
        "SELECT id FROM subscriptions WHERE user_id=? AND notes=?",
        [$userId, "{$gateway}:{$ref}"]
    );
    if ($existing) {
        return; // Already activated — nothing to do.
    }

    $days    = $billing === 'yearly' ? 365 : 31;
    $expires = date('Y-m-d H:i:s', strtotime("+{$days} days"));

    // Upgrade the user's plan.
    get_db()->prepare("UPDATE users SET plan=? WHERE id=?")->execute([$plan, $userId]);

    // Cancel any previously active subscription rows before inserting the new one.
    get_db()->prepare(
        "UPDATE subscriptions SET status='cancelled', cancelled_at=datetime('now')
          WHERE user_id=? AND status='active'"
    )->execute([$userId]);

    // Insert the new subscription row with the tx ref as a natural idempotency key.
    db_run(
        "INSERT INTO subscriptions (user_id, plan, status, amount, billing_cycle, expires_at, notes)
         VALUES (?, ?, 'active', ?, ?, ?, ?)",
        [$userId, $plan, $amount, $billing, $expires, "{$gateway}:{$ref}"]
    );

    // Confirmation Email
    $user = db_one("SELECT name, email FROM users WHERE id=?", [$userId]);
    if ($user) {
        send_payment_success_email($user['email'], $user['name'], $plan, $amount);
    }
}
