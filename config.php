<?php
/**
 * Load .env variables into environment
 */
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name  = trim($name);
        $value = trim($value);
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// ── SOLEN CONFIGURATION ────────────────────────────────────────────────────
// Edit these values after deployment, or set as environment variables

$dbEnv = getenv('DB_PATH') ?: 'database/solen.db';
define('DB_PATH', (strpos($dbEnv, ':') === false && strpos($dbEnv, '/') !== 0) ? __DIR__ . '/' . $dbEnv : $dbEnv);
define('SITE_NAME',        'Solen');
define('SITE_URL',         getenv('SITE_URL') ?: 'https://getsolen.com');
define('ADMIN_EMAIL',      getenv('ADMIN_EMAIL') ?: 'admin@getsolen.com');
define('SESSION_LIFETIME', 86400 * 30); // 30 days

// ── AI PROVIDER KEYS ──────────────────────────────────────────────────────
define('CLAUDE_API_KEY',      getenv('CLAUDE_API_KEY')      ?: '');
define('GEMINI_API_KEY',      getenv('GEMINI_API_KEY')      ?: '');
define('HUGGINGFACE_API_KEY', getenv('HUGGINGFACE_API_KEY') ?: '');
define('OPENROUTER_API_KEY',  getenv('OPENROUTER_API_KEY')  ?: '');
define('GROQ_API_KEY',        getenv('GROQ_API_KEY')        ?: '');
define('HYPEREAL_API_KEY',    getenv('HYPEREAL_API_KEY')    ?: '');
define('FIREWORKS_API_KEY',   getenv('FIREWORKS_API_KEY')   ?: '');
define('PUTER_AUTH_TOKEN',    getenv('PUTER_AUTH_TOKEN')    ?: '');

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
