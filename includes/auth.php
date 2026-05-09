<?php
require_once __DIR__ . '/db.php';

// ── Session bootstrap ──────────────────────────────────────────────────────
// We use PHP $_SESSION as a lightweight cache of the token stored in the
// `user_sessions` DB table. This gives us real server-side revocation
// ("log out all devices") while keeping the cookie-session UX unchanged.

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 0,           // expire on browser close; DB token governs 30-day persistence
        'cookie_secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // HTTPS only if available
        'cookie_httponly' => true,         // no JS access
        'cookie_samesite' => 'Lax',        // blocks CSRF on cross-site navigations
        'use_strict_mode' => true,         // reject unrecognised session IDs
    ]);
}

// ── Helpers ────────────────────────────────────────────────────────────────

/** Validate that the current PHP session has a live row in user_sessions. */
function _session_is_valid(): bool {
    if (empty($_SESSION['user_id']) || empty($_SESSION['db_token'])) return false;
    $row = db_one(
        "SELECT id FROM user_sessions WHERE token=? AND user_id=? AND expires_at > datetime('now')",
        [$_SESSION['db_token'], $_SESSION['user_id']]
    );
    return (bool)$row;
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

    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['db_token']  = $token;

    db_run("UPDATE users SET last_login=datetime('now') WHERE id=?", [$user['id']]);
    return true;
}

function logout_user(): void {
    if (!empty($_SESSION['db_token'])) {
        db_run("DELETE FROM user_sessions WHERE token=?", [$_SESSION['db_token']]);
    }
    session_destroy();
    header('Location: /login.php');
    exit;
}

/** Revoke every active session for a user ("log out all devices"). */
function logout_all_devices(int $userId): void {
    db_run("DELETE FROM user_sessions WHERE user_id=?", [$userId]);
    if (($_SESSION['user_id'] ?? 0) == $userId) {
        session_destroy();
    }
}

function is_logged_in(): bool {
    if (!_session_is_valid()) {
        session_destroy();
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
