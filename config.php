<?php
// ── SOLEN CONFIGURATION ────────────────────────────────────────────────────
// Edit these values after deployment, or set as environment variables

define('DB_PATH',          __DIR__ . '/database/solen.db');
define('SITE_NAME',        'Solen');
define('SITE_URL',         getenv('SITE_URL') ?: 'https://getsolen.com');
define('ADMIN_EMAIL',      getenv('ADMIN_EMAIL') ?: 'admin@getsolen.com');
define('SESSION_LIFETIME', 86400 * 30); // 30 days

// ── AI PROVIDER KEYS ──────────────────────────────────────────────────────
define('CLAUDE_API_KEY',      getenv('CLAUDE_API_KEY')      ?: '');
define('GEMINI_API_KEY',      getenv('GEMINI_API_KEY')      ?: '');
define('HUGGINGFACE_API_KEY', getenv('HUGGINGFACE_API_KEY') ?: '');
define('OPENROUTER_API_KEY',  getenv('OPENROUTER_API_KEY')  ?: '');

// ── PAYMENT GATEWAY KEYS ──────────────────────────────────────────────────
define('STRIPE_PK',              getenv('STRIPE_PK')              ?: '');
define('STRIPE_SK',              getenv('STRIPE_SK')              ?: '');
define('STRIPE_WEBHOOK_SECRET',  getenv('STRIPE_WEBHOOK_SECRET')  ?: '');

define('FLUTTERWAVE_PK',         getenv('FLUTTERWAVE_PK')         ?: '');
define('FLUTTERWAVE_SK',         getenv('FLUTTERWAVE_SK')         ?: '');
define('FLUTTERWAVE_ENCRYPTION', getenv('FLUTTERWAVE_ENCRYPTION') ?: '');

define('PAYSTACK_PK',            getenv('PAYSTACK_PK')            ?: '');
define('PAYSTACK_SK',            getenv('PAYSTACK_SK')            ?: '');

// ── PLANS ─────────────────────────────────────────────────────────────────
define('PLANS', [
    'free'    => ['name' => 'Free Trial',     'price' => 0,     'days_trial' => 7],
    'plus'    => ['name' => 'Solen Plus',     'price' => 5.99,  'yearly' => 59.99],
    'pro'     => ['name' => 'Solen Pro',      'price' => 12.99, 'yearly' => 99.00],
    'premium' => ['name' => 'Solen Premium',  'price' => 24.99, 'yearly' => 179.00],
]);

date_default_timezone_set('UTC');
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
