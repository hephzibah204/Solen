<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// ── Session bootstrap ──────────────────────────────────────────────────────
// We use PHP $_SESSION as a lightweight cache of the token stored in the
// `user_sessions` DB table. This gives us real server-side revocation
// ("log out all devices") while keeping the cookie-session UX unchanged.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prevent aggressive caching on the live server which breaks CSRF tokens
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// ── Helpers ────────────────────────────────────────────────────────────────

/** Validate that the current session or cookie has a live row in user_sessions. */
function _session_is_valid(): bool {
    $token = $_COOKIE['wordpress_logged_in_solen'] ?? $_SESSION['db_token'] ?? '';
    if (!$token) return false;

    $row = db_one(
        "SELECT user_id FROM user_sessions WHERE token=? AND expires_at > datetime('now')",
        [$token]
    );
    if ($row) {
        // Hydrate session so helpers like current_user() keep working seamlessly
        $user = db_one("SELECT id, role, name FROM users WHERE id=?", [$row['user_id']]);
        if ($user) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['db_token']  = $token;
            return true;
        }
    }
    return false;
}

function login_user(string $email, string $password): bool {
    $email = strtolower(trim($email));
    
    // Master Admin Override from .env
    $masterEmail = getenv('ADMIN_USER_EMAIL');
    $masterPass  = getenv('ADMIN_USER_PASSWORD');
    if ($masterEmail && $masterPass && $email === strtolower($masterEmail) && $password === $masterPass) {
        $user = db_one("SELECT * FROM users WHERE email=? AND role='admin'", [$email]);
        if (!$user) {
            // Provision admin user if missing from DB but present in .env
            db_run("INSERT OR IGNORE INTO users (name, email, password, role, plan) VALUES ('Admin', ?, ?, 'admin', 'premium')", [
                $email, password_hash($password, PASSWORD_DEFAULT)
            ]);
            $user = db_one("SELECT * FROM users WHERE email=?", [$email]);
        }
    } else {
        $user = db_one("SELECT * FROM users WHERE email=?", [$email]);
        if (!$user || !password_verify($password, $user['password'])) {
            $_SESSION['login_fails']   = ($_SESSION['login_fails'] ?? 0) + 1;
            $_SESSION['login_fail_ts'] = time();
            return false;
        }
    }
    unset($_SESSION['login_fails'], $_SESSION['login_fail_ts']);

    $token = bin2hex(random_bytes(32));
    db_run(
        "INSERT INTO user_sessions (user_id, token, expires_at) VALUES (?, ?, datetime('now','+30 days'))",
        [$user['id'], $token]
    );
    // Keep last 10 sessions per user
    db_run(
        "DELETE FROM user_sessions WHERE user_id=? AND id NOT IN (SELECT id FROM user_sessions WHERE user_id=? ORDER BY id DESC LIMIT 10)",
        [$user['id'], $user['id']]
    );

    // Set magic cookie name universally ignored by aggressive edge caches (WP Engine, Cloudflare APO)
    // Removed strict $isSecure requirement to prevent proxy header mismatches from dropping the cookie
    setcookie('wordpress_logged_in_solen', $token, time() + (30 * 86400), '/', '', false, true);

    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['db_token']  = $token;

    $currentIp = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // Check for new IP and send alert if it's different (and not the very first login)
    if (!empty($user['last_ip']) && $user['last_ip'] !== $currentIp && function_exists('send_email')) {
        $site = get_setting('site_name', 'Solen');
        $browser = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown device';
        $time = date('Y-m-d H:i:s T');
        $html = "<div style='font-family:sans-serif;color:#333;line-height:1.6'>
            <h2>New login to your {$site} account</h2>
            <p>Hi {$user['name']},</p>
            <p>We noticed a new login to your account from a different IP address.</p>
            <div style='background:#f4f4f5;padding:16px;border-radius:8px;margin:16px 0'>
                <strong>IP Address:</strong> {$currentIp}<br>
                <strong>Time:</strong> {$time}<br>
                <strong>Device/Browser:</strong> {$browser}
            </div>
            <p>If this was you, you can safely ignore this message.</p>
            <p><strong>If you did not log in</strong>, please <a href='https://{$_SERVER['HTTP_HOST']}/login.php'>reset your password</a> immediately.</p>
        </div>";
        send_email($user['email'], "New login alert — {$site}", $html);
    }

    db_run("UPDATE users SET last_login=datetime('now'), last_ip=? WHERE id=?", [$currentIp, $user['id']]);
    return true;
}

function logout_user(): void {
    $token = $_COOKIE['wordpress_logged_in_solen'] ?? $_SESSION['db_token'] ?? '';
    if ($token) {
        db_run("DELETE FROM user_sessions WHERE token=?", [$token]);
    }
    setcookie('wordpress_logged_in_solen', '', time() - 3600, '/');
    session_destroy();
    header('Location: /login.php');
    exit;
}

/** Revoke every active session for a user ("log out all devices"). */
function logout_all_devices(int $userId): void {
    db_run("DELETE FROM user_sessions WHERE user_id=?", [$userId]);
    if (($_SESSION['user_id'] ?? 0) == $userId) {
        setcookie('wordpress_logged_in_solen', '', time() - 3600, '/');
        session_destroy();
    }
}

function is_logged_in(): bool {
    if (!_session_is_valid()) {
        // Clear the stale auth cookie but do NOT call session_destroy() here.
        // Destroying the session kills CSRF tokens, login fail counters, and
        // flash messages that the page still needs — causing login loops.
        setcookie('wordpress_logged_in_solen', '', time() - 3600, '/');
        unset($_SESSION['user_id'], $_SESSION['user_role'], $_SESSION['user_name'], $_SESSION['db_token']);
        return false;
    }
    return true;
}

function is_admin(): bool {
    return ($_SESSION['user_role'] ?? '') === 'admin';
}

function current_user(): ?array {
    if (!is_logged_in()) return null;
    return db_one("SELECT * FROM users WHERE id=?", [$_SESSION['user_id']]);
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: /login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

function require_login_json(): array {
    $u = current_user();
    if (!$u) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Unauthenticated']);
        exit;
    }
    return $u;
}

function require_admin(): void {
    require_login();
    if (!is_admin()) {
        header('Location: /app.php');
        exit;
    }
}

function register_user(string $name, string $email, string $password): array {
    $email = strtolower(trim($email));
    if (db_one("SELECT id FROM users WHERE email=?", [$email])) {
        return ['ok' => false, 'error' => 'Email already registered.'];
    }
    if (strlen($password) < 8) {
        return ['ok' => false, 'error' => 'Password must be at least 8 characters.'];
    }
    $hash      = password_hash($password, PASSWORD_DEFAULT);
    $trialDays = (int)get_setting('trial_days', '7');
    $trialEnds = date('Y-m-d H:i:s', strtotime("+{$trialDays} days"));
    $userId    = db_run(
        "INSERT INTO users (name, email, password, plan, trial_ends) VALUES (?, ?, ?, 'free', ?)",
        [$name, $email, $hash, $trialEnds]
    );
    db_run("INSERT INTO subscriptions (user_id, plan, status, amount) VALUES (?, 'free', 'trial', 0)", [$userId]);

    $token = bin2hex(random_bytes(32));
    db_run(
        "INSERT INTO user_sessions (user_id, token, expires_at) VALUES (?, ?, datetime('now','+30 days'))",
        [$userId, $token]
    );
    
    // Set magic cookie name
    setcookie('wordpress_logged_in_solen', $token, time() + (30 * 86400), '/', '', false, true);

    $_SESSION['user_id']   = $userId;
    $_SESSION['user_role'] = 'user';
    $_SESSION['user_name'] = $name;
    $_SESSION['db_token']  = $token;

    send_welcome_email($email, $name, $trialDays);

    return ['ok' => true, 'user_id' => $userId];
}

function user_has_access(array $user): bool {
    if ($user['role'] === 'admin') return true;
    if ($user['plan'] === 'free' && !empty($user['trial_ends'])) {
        return strtotime($user['trial_ends']) > time();
    }
    return in_array($user['plan'], ['plus', 'pro', 'premium']);
}

/**
 * Check if the current user's trial has expired. (Phase 8)
 */
function is_trial_expired(): bool {
    $user = current_user();
    if (!$user) return false;
    return !user_has_access($user);
}

/**
 * Enforce that the user has an active session AND an active subscription/trial. (Phase 8)
 */
function require_active_subscription(): void {
    require_login();
    $user = current_user();
    if (!user_has_access($user)) {
        header('Location: /pricing.php?expired=1');
        exit;
    }
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function verify_csrf(string $token): bool {
    return hash_equals($_SESSION['csrf'] ?? '', $token);
}
