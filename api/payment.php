<?php
/**
 * /api/payment.php — Payment gateway handler
 * Supports: Stripe, Flutterwave, Paystack
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (!is_logged_in()) { http_response_code(401); die('Unauthorized'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); die('Method Not Allowed'); }

$body     = json_decode(file_get_contents('php://input'), true);
$action   = $body['action']   ?? '';
$gateway  = strtolower($body['gateway']  ?? get_setting('payment_gateway', 'stripe'));
$plan     = $body['plan']     ?? 'pro';
$billing  = $body['billing']  ?? 'monthly'; // monthly|yearly
$user     = current_user();

header('Content-Type: application/json');

switch ($action) {
    case 'initiate': handle_initiate($gateway, $plan, $billing, $user); break;
    case 'verify':   handle_verify($gateway, $body, $user);             break;
    case 'simulate_success': handle_simulate_success($body, $user);     break;
    default:         json_err('Unknown action');
}

// ── SIMULATE SUCCESS (Phase 8 - Dev only) ───────────────────────────────
function handle_simulate_success(array $body, array $user): void {
    // In production, you would check if the user is an admin or if a DEV_MODE flag is on
    $plan    = $body['plan'] ?? 'pro';
    $billing = $body['billing'] ?? 'monthly';
    $amount  = $billing === 'yearly' ? 99.00 : 12.99;
    
    activate_plan((int)$user['id'], $plan, $billing, $amount, 'simulated', 'sim_' . time());
    echo json_encode(['status' => 'success']);
    exit;
}

// ── INITIATE ─────────────────────────────────────────────────────────────
function handle_initiate(string $gateway, string $plan, string $billing, array $user): void {
    $amount = get_plan_amount($plan, $billing);

    switch ($gateway) {
        case 'flutterwave': initiate_flutterwave($user, $plan, $billing, $amount); break;
        case 'paystack':    initiate_paystack($user, $plan, $billing, $amount);    break;
        default:            initiate_stripe($user, $plan, $billing, $amount);       break;
    }
}

// ── STRIPE ────────────────────────────────────────────────────────────────
function initiate_stripe(array $user, string $plan, string $billing, float $amount): void {
    $sk = get_setting('stripe_sk') ?: STRIPE_SK;
    if (!$sk) { json_err('Stripe not configured.'); return; }

    $priceId = get_setting("stripe_price_{$plan}_{$billing}");
    if (!$priceId) { json_err("Stripe price ID for {$plan}/{$billing} not set in Admin → Settings."); return; }

    $payload = http_build_query([
        'mode'                   => 'subscription',
        'customer_email'         => $user['email'],
        'line_items[0][price]'   => $priceId,
        'line_items[0][quantity]'=> '1',
        'success_url'            => SITE_URL . '/upgrade-success.php?session_id={CHECKOUT_SESSION_ID}&gateway=stripe',
        'cancel_url'             => SITE_URL . '/pricing.php',
        'metadata[user_id]'      => $user['id'],
        'metadata[plan]'         => $plan,
        'metadata[billing]'      => $billing,
    ]);

    $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_USERPWD        => $sk . ':',
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $res  = curl_exec($ch);
    $data = json_decode($res, true);
    curl_close($ch);

    if (!empty($data['url'])) {
        echo json_encode(['url' => $data['url']]);
    } else {
        json_err($data['error']['message'] ?? 'Stripe error');
    }
}

// ── FLUTTERWAVE ───────────────────────────────────────────────────────────
function initiate_flutterwave(array $user, string $plan, string $billing, float $amount): void {
    $pk  = get_setting('flutterwave_pk') ?: FLUTTERWAVE_PK;
    $sk  = get_setting('flutterwave_sk') ?: FLUTTERWAVE_SK;
    if (!$sk) { json_err('Flutterwave not configured.'); return; }

    $txRef  = 'solen_' . $user['id'] . '_' . time();
    $payload = json_encode([
        'tx_ref'          => $txRef,
        'amount'          => $amount,
        'currency'        => 'USD',
        'redirect_url'    => SITE_URL . '/upgrade-success.php?gateway=flutterwave&tx_ref=' . $txRef,
        'customer'        => ['email' => $user['email'], 'name' => $user['name']],
        'customizations'  => [
            'title'       => 'Solen Wellness',
            'description' => 'Solen ' . ucfirst($plan) . ' — ' . ucfirst($billing),
            'logo'        => SITE_URL . '/assets/logo.png',
        ],
        'meta'            => ['user_id' => $user['id'], 'plan' => $plan, 'billing' => $billing],
    ]);

    $ch = curl_init('https://api.flutterwave.com/v3/payments');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $sk,
        ],
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $res  = curl_exec($ch);
    $data = json_decode($res, true);
    curl_close($ch);

    if (!empty($data['data']['link'])) {
        // Store pending tx
        db_run("INSERT INTO payment_transactions (user_id,gateway,tx_ref,plan,billing,amount,status) VALUES (?,?,?,?,?,?,'pending')",
               [$user['id'], 'flutterwave', $txRef, $plan, $billing, $amount]);
        echo json_encode(['url' => $data['data']['link']]);
    } else {
        json_err($data['message'] ?? 'Flutterwave error');
    }
}

// ── PAYSTACK ─────────────────────────────────────────────────────────────
function initiate_paystack(array $user, string $plan, string $billing, float $amount): void {
    $sk = get_setting('paystack_sk') ?: PAYSTACK_SK;
    if (!$sk) { json_err('Paystack not configured.'); return; }

    $ref = 'solen_' . $user['id'] . '_' . time();
    $payload = json_encode([
        'email'        => $user['email'],
        'amount'       => (int)($amount * 100), // Paystack uses kobo/cents
        'currency'     => 'USD',
        'reference'    => $ref,
        'callback_url' => SITE_URL . '/upgrade-success.php?gateway=paystack&reference=' . $ref,
        'metadata'     => ['user_id' => $user['id'], 'plan' => $plan, 'billing' => $billing,
                           'custom_fields' => [['display_name'=>'Plan','variable_name'=>'plan','value'=>$plan]]],
    ]);

    $ch = curl_init('https://api.paystack.co/transaction/initialize');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $sk,
        ],
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $res  = curl_exec($ch);
    $data = json_decode($res, true);
    curl_close($ch);

    if (!empty($data['data']['authorization_url'])) {
        db_run("INSERT INTO payment_transactions (user_id,gateway,tx_ref,plan,billing,amount,status) VALUES (?,?,?,?,?,?,'pending')",
               [$user['id'], 'paystack', $ref, $plan, $billing, $amount]);
        echo json_encode(['url' => $data['data']['authorization_url']]);
    } else {
        json_err($data['message'] ?? 'Paystack error');
    }
}

// ── VERIFY ────────────────────────────────────────────────────────────────
function handle_verify(string $gateway, array $body, array $user): void {
    switch ($gateway) {
        case 'flutterwave': verify_flutterwave($body, $user); break;
        case 'paystack':    verify_paystack($body, $user);    break;
        default:            echo json_encode(['status' => 'redirect']);
    }
}

function verify_flutterwave(array $body, array $user): void {
    $sk     = get_setting('flutterwave_sk') ?: FLUTTERWAVE_SK;
    $txId   = $body['transaction_id'] ?? '';
    if (!$txId) { json_err('Missing transaction_id'); return; }

    $ch = curl_init("https://api.flutterwave.com/v3/transactions/{$txId}/verify");
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $sk],
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $res  = curl_exec($ch);
    $data = json_decode($res, true);
    curl_close($ch);

    if (($data['data']['status'] ?? '') === 'successful') {
        $meta = $data['data']['meta'] ?? [];
        activate_plan($meta['user_id'] ?? $user['id'], $meta['plan'] ?? 'pro', $meta['billing'] ?? 'monthly',
                      $data['data']['amount'], 'flutterwave', $txId);
        echo json_encode(['status' => 'success']);
    } else {
        json_err('Payment not successful');
    }
}

function verify_paystack(array $body, array $user): void {
    $sk  = get_setting('paystack_sk') ?: PAYSTACK_SK;
    $ref = $body['reference'] ?? '';
    if (!$ref) { json_err('Missing reference'); return; }

    $ch = curl_init("https://api.paystack.co/transaction/verify/{$ref}");
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $sk],
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $res  = curl_exec($ch);
    $data = json_decode($res, true);
    curl_close($ch);

    if (($data['data']['status'] ?? '') === 'success') {
        $meta = $data['data']['metadata'] ?? [];
        activate_plan($meta['user_id'] ?? $user['id'], $meta['plan'] ?? 'pro', 'monthly',
                      ($data['data']['amount'] / 100), 'paystack', $ref);
        echo json_encode(['status' => 'success']);
    } else {
        json_err('Payment not successful');
    }
}

function get_plan_amount(string $plan, string $billing): float {
    $key = $billing === 'yearly' ? "price_{$plan}_yearly" : "price_{$plan}_monthly";
    return (float)(get_setting($key) ?: (PLANS[$plan]['price'] ?? 0));
}

function json_err(string $msg): void {
    http_response_code(400);
    echo json_encode(['error' => $msg]);
    exit;
}
