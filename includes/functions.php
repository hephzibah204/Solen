<?php

// ── EMAIL SYSTEM ───────────────────────────────────────────────────────────

/**
 * Send an email via SMTP (settings from DB) or PHP mail() as fallback.
 * Returns true on success, false on failure.
 */
function send_email(string $to, string $subject, string $htmlBody, string $textBody = ''): bool {
    $host     = get_setting('smtp_host');
    $port     = (int)(get_setting('smtp_port') ?: 587);
    $user     = get_setting('smtp_user');
    $pass     = get_setting('smtp_pass');
    $fromAddr = get_setting('from_email', 'hello@getsolen.com');
    $fromName = get_setting('from_name',  'Solen');

    if (!$textBody) {
        $textBody = html_to_text($htmlBody);
    }

    // ── SMTP path ──────────────────────────────────────────────────────────
    if ($host && $user && $pass) {
        return smtp_send($host, $port, $user, $pass, $fromAddr, $fromName, $to, $subject, $htmlBody, $textBody);
    }

    // ── php mail() fallback ────────────────────────────────────────────────
    $boundary = 'solen_' . md5(uniqid());
    $headers  = implode("\r\n", [
        "From: {$fromName} <{$fromAddr}>",
        "Reply-To: {$fromAddr}",
        'MIME-Version: 1.0',
        "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
        'X-Mailer: Solen/1.0',
    ]);
    $body = "--{$boundary}\r\n"
          . "Content-Type: text/plain; charset=UTF-8\r\n\r\n{$textBody}\r\n\r\n"
          . "--{$boundary}\r\n"
          . "Content-Type: text/html; charset=UTF-8\r\n\r\n{$htmlBody}\r\n\r\n"
          . "--{$boundary}--";

    return @mail($to, $subject, $body, $headers);
}

/**
 * Raw SMTP client — supports STARTTLS, AUTH LOGIN.
 * No external library required.
 */
function smtp_send(
    string $host, int $port,
    string $user, string $pass,
    string $fromAddr, string $fromName,
    string $to, string $subject,
    string $htmlBody, string $textBody
): bool {
    $timeout = 15;
    $useTls  = ($port === 587 || $port === 25);
    $useSSL  = ($port === 465);

    $addr = ($useSSL ? 'ssl://' : '') . $host;

    $sock = @stream_socket_client("{$addr}:{$port}", $errno, $errstr, $timeout);
    if (!$sock) {
        error_log("Solen SMTP: connect failed to {$host}:{$port} — {$errstr}");
        return false;
    }
    stream_set_timeout($sock, $timeout);

    $read = fn() => fgets($sock, 512);
    $send = function(string $cmd) use ($sock, $read): string {
        fwrite($sock, $cmd . "\r\n");
        return $read();
    };

    // Greeting
    $read();
    $send("EHLO " . gethostname());

    if ($useTls) {
        $resp = $send("STARTTLS");
        if (strpos($resp, '220') !== 0) {
            fclose($sock); return false;
        }
        if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($sock); return false;
        }
        $send("EHLO " . gethostname());
    }

    // Auth
    $send("AUTH LOGIN");
    $send(base64_encode($user));
    $authResp = $send(base64_encode($pass));
    if (strpos($authResp, '235') === false) {
        error_log("Solen SMTP: AUTH failed — {$authResp}");
        fclose($sock); return false;
    }

    $send("MAIL FROM:<{$fromAddr}>");
    $send("RCPT TO:<{$to}>");
    $send("DATA");

    $boundary = 'solen_' . md5(uniqid());
    $date     = date('r');
    $msgId    = '<' . uniqid('solen_', true) . '@' . gethostname() . '>';

    $message = "Date: {$date}\r\n"
             . "From: {$fromName} <{$fromAddr}>\r\n"
             . "To: {$to}\r\n"
             . "Subject: {$subject}\r\n"
             . "Message-ID: {$msgId}\r\n"
             . "MIME-Version: 1.0\r\n"
             . "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n\r\n"
             . "--{$boundary}\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
             . "{$textBody}\r\n\r\n"
             . "--{$boundary}\r\n"
             . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
             . "{$htmlBody}\r\n\r\n"
             . "--{$boundary}--\r\n"
             . ".";

    $resp = $send($message);
    $send("QUIT");
    fclose($sock);

    if (strpos($resp, '250') === false) {
        error_log("Solen SMTP: DATA rejected — {$resp}");
        return false;
    }
    return true;
}

/** Strip HTML tags to produce a plain-text fallback. */
function html_to_text(string $html): string {
    $text = preg_replace('/<br\s*\/?>/i', "\n", $html);
    $text = preg_replace('/<\/p>/i',      "\n\n", $text);
    $text = preg_replace('/<\/h[1-6]>/i', "\n\n", $text);
    $text = strip_tags($text);
    return trim(preg_replace("/\n{3,}/", "\n\n", $text));
}

// ── EMAIL TEMPLATES ────────────────────────────────────────────────────────

/** Wrap content in the standard Solen HTML email shell. */
function email_layout(string $content, string $preheader = ''): string {
    $site    = get_setting('site_name', 'Solen');
    $siteUrl = rtrim(get_setting('canonical_url') ?: SITE_URL, '/');
    $year    = date('Y');
    $pre     = $preheader
        ? "<div style='display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;color:#07070f;'>{$preheader}&nbsp;</div>"
        : '';
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<meta http-equiv="X-UA-Compatible" content="IE=edge"/>
<title>{$site}</title>
</head>
<body style="margin:0;padding:0;background:#07070f;font-family:'Georgia',serif;-webkit-font-smoothing:antialiased;">
{$pre}
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#07070f;">
  <tr><td align="center" style="padding:40px 16px;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;width:100%;">

      <!-- HEADER -->
      <tr><td align="center" style="padding-bottom:32px;">
        <a href="{$siteUrl}" style="font-family:'Georgia',serif;font-size:28px;color:#c5a572;text-decoration:none;letter-spacing:0.04em;">{$site}</a>
      </td></tr>

      <!-- BODY CARD -->
      <tr><td style="background:#0e0e1a;border:1px solid rgba(255,255,255,0.07);border-radius:20px;padding:40px 40px 36px;color:#f0ede8;">
        {$content}
      </td></tr>

      <!-- FOOTER -->
      <tr><td align="center" style="padding-top:28px;font-size:12px;color:rgba(240,237,232,0.3);line-height:1.8;">
        <a href="{$siteUrl}" style="color:rgba(240,237,232,0.3);text-decoration:none;">{$site}</a>
        &nbsp;·&nbsp;
        <a href="{$siteUrl}/login.php" style="color:rgba(240,237,232,0.3);text-decoration:none;">Sign in</a>
        &nbsp;·&nbsp;
        <a href="{$siteUrl}/pricing.php" style="color:rgba(240,237,232,0.3);text-decoration:none;">Pricing</a>
        <br/>© {$year} {$site} Inc. All rights reserved.
        <br/>Solen is not a medical or mental health service.
      </td></tr>

    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
}

/**
 * Send welcome email immediately after signup.
 * Called from auth.php → register_user().
 */
function send_welcome_email(string $to, string $name, int $trialDays): bool {
    $site    = get_setting('site_name', 'Solen');
    $siteUrl = rtrim(get_setting('canonical_url') ?: SITE_URL, '/');
    $first   = explode(' ', trim($name))[0];

    $content = <<<HTML
<h1 style="margin:0 0 8px;font-family:'Georgia',serif;font-size:28px;font-weight:400;color:#f0ede8;">Welcome, {$first}. 🌿</h1>
<p style="margin:0 0 24px;font-size:15px;color:rgba(240,237,232,0.55);line-height:1.6;">Your {$trialDays}-day free trial has started.</p>

<p style="margin:0 0 20px;font-size:16px;color:#f0ede8;line-height:1.75;">
  You now have full access to {$site} — your personal AI wellness coach. Your coach will learn your patterns, remember what you share, and show up for you every day.
</p>

<p style="margin:0 0 8px;font-size:14px;color:rgba(240,237,232,0.55);font-weight:500;letter-spacing:0.06em;text-transform:uppercase;">To get started:</p>
<table cellpadding="0" cellspacing="0" border="0" style="margin-bottom:28px;">
  <tr><td style="padding:6px 0;font-size:15px;color:#f0ede8;">✦ &nbsp;Choose your coach's focus and name</td></tr>
  <tr><td style="padding:6px 0;font-size:15px;color:#f0ede8;">✦ &nbsp;Log your first mood check-in</td></tr>
  <tr><td style="padding:6px 0;font-size:15px;color:#f0ede8;">✦ &nbsp;Start a 7-day growth program</td></tr>
</table>

<table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:28px;">
  <tr><td align="center">
    <a href="{$siteUrl}/app.php" style="display:inline-block;background:#c5a572;color:#1a1008;padding:14px 36px;border-radius:50px;text-decoration:none;font-family:Arial,sans-serif;font-size:15px;font-weight:600;letter-spacing:0.02em;">Open Your Coach →</a>
  </td></tr>
</table>

<p style="margin:0;font-size:13px;color:rgba(240,237,232,0.4);line-height:1.7;">
  Your trial runs for {$trialDays} days — no credit card needed, and you can upgrade anytime from within the app.
  <br/>Questions? Reply to this email and we'll help.
</p>
HTML;

    $html = email_layout($content, "Your {$trialDays}-day free trial has started — open your coach now.");
    return send_email($to, "Welcome to {$site} — your coach is ready 🌿", $html);
}

/**
 * Send trial expiry warning emails.
 * Intended to be called from a daily cron job or admin trigger.
 * $daysLeft = 3 (3-day warning) or 1 (final day warning).
 */
function send_trial_warning_emails(int $daysLeft): int {
    $siteUrl = rtrim(get_setting('canonical_url') ?: SITE_URL, '/');
    $site    = get_setting('site_name', 'Solen');

    // Find users whose trial ends in exactly $daysLeft days (within a 24-hour window)
    $from = date('Y-m-d', strtotime("+{$daysLeft} days"));
    $to   = date('Y-m-d', strtotime("+{$daysLeft} days + 1 day"));

    $users = db_query(
        "SELECT * FROM users WHERE plan='free' AND trial_ends >= ? AND trial_ends < ?",
        [$from . ' 00:00:00', $to . ' 00:00:00']
    );

    $sent = 0;
    foreach ($users as $user) {
        $first = explode(' ', trim($user['name']))[0];

        if ($daysLeft === 3) {
            $urgency = "3 days left on your free trial";
            $body    = "Your Solen free trial ends in 3 days. Don't lose your coach, your mood history, or the memories your coach has built about you.";
            $cta     = "Keep Your Progress →";
            $preheader = "3 days left — upgrade to keep your coach and all your progress.";
        } else {
            $urgency = "Last day of your free trial";
            $body    = "Today is the last day of your Solen free trial. After midnight, your account moves to read-only — but your data will never be deleted.";
            $cta     = "Upgrade Before It Ends →";
            $preheader = "Today's your last day — upgrade now to keep full access.";
        }

        $content = <<<HTML
<h1 style="margin:0 0 8px;font-family:'Georgia',serif;font-size:26px;font-weight:400;color:#f0ede8;">{$first}, {$urgency}.</h1>
<p style="margin:0 0 24px;font-size:14px;color:rgba(240,237,232,0.45);">A quick note from your coach.</p>

<p style="margin:0 0 20px;font-size:16px;color:#f0ede8;line-height:1.75;">{$body}</p>

<p style="margin:0 0 20px;font-size:16px;color:#f0ede8;line-height:1.75;">
  Upgrade to <strong style="color:#c5a572;">Solen Pro</strong> for just $12.99/month — and your coach keeps every memory, every mood entry, and every insight you've built so far.
</p>

<table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
  <tr><td align="center">
    <a href="{$siteUrl}/pricing.php" style="display:inline-block;background:#c5a572;color:#1a1008;padding:14px 36px;border-radius:50px;text-decoration:none;font-family:Arial,sans-serif;font-size:15px;font-weight:600;">{$cta}</a>
  </td></tr>
</table>

<p style="margin:0;font-size:13px;color:rgba(240,237,232,0.4);line-height:1.7;">
  30-day money-back guarantee on all paid plans. No questions asked.
  <br/>You can also continue for free — your data will always be here.
</p>
HTML;

        $html = email_layout($content, $preheader);
        if (send_email($user['email'], "{$site}: {$urgency}", $html)) {
            $sent++;
        }
    }
    return $sent;
}

/**
 * Send a password reset email.
 * $token is the raw reset token (not hashed).
 */
function send_payment_success_email(string $to, string $name, string $plan, float $amount): bool {
    $site    = get_setting('site_name', 'Solen');
    $siteUrl = rtrim(get_setting('canonical_url') ?: SITE_URL, '/');
    $first   = explode(' ', trim($name))[0];
    $planName = ucfirst($plan);
    $price    = number_format($amount, 2);

    $content = <<<HTML
<h1 style="margin:0 0 8px;font-family:'Georgia',serif;font-size:26px;font-weight:400;color:#f0ede8;">Payment received, {$first}. ✦</h1>
<p style="margin:0 0 24px;font-size:15px;color:rgba(240,237,232,0.45);line-height:1.6;">Your {$planName} subscription is now active.</p>

<p style="margin:0 0 20px;font-size:16px;color:#f0ede8;line-height:1.75;">
  Thank you for supporting Solen. Your payment of <strong>\${$price}</strong> has been processed successfully. Your coach now has full access to all premium features, including advanced memory, voice sessions, and growth programs.
</p>

<table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
  <tr><td align="center">
    <a href="{$siteUrl}/app.php" style="display:inline-block;background:#c5a572;color:#1a1008;padding:14px 36px;border-radius:50px;text-decoration:none;font-family:Arial,sans-serif;font-size:15px;font-weight:600;">Back to your Coach →</a>
  </td></tr>
</table>

<p style="margin:0;font-size:13px;color:rgba(240,237,232,0.35);line-height:1.7;">
  You can manage your subscription anytime from your dashboard settings.
  <br/>Need a formal PDF invoice? Just reply to this email.
</p>
HTML;

    $html = email_layout($content, "Confirmation: Your payment for Solen {$planName} was successful.");
    return send_email($to, "Subscription Confirmed — Solen {$planName}", $html);
}

function send_password_reset_email(string $to, string $name, string $token): bool {
    $site    = get_setting('site_name', 'Solen');
    $siteUrl = rtrim(get_setting('canonical_url') ?: SITE_URL, '/');
    $first   = explode(' ', trim($name))[0];
    $link    = "{$siteUrl}/reset.php?token=" . urlencode($token);

    $content = <<<HTML
<h1 style="margin:0 0 8px;font-family:'Georgia',serif;font-size:26px;font-weight:400;color:#f0ede8;">Reset your password</h1>
<p style="margin:0 0 24px;font-size:14px;color:rgba(240,237,232,0.45);">Hi {$first} — we received a request to reset your password.</p>

<p style="margin:0 0 20px;font-size:16px;color:#f0ede8;line-height:1.75;">
  Click the button below to choose a new password. This link expires in <strong>1 hour</strong>.
</p>

<table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
  <tr><td align="center">
    <a href="{$link}" style="display:inline-block;background:#c5a572;color:#1a1008;padding:14px 36px;border-radius:50px;text-decoration:none;font-family:Arial,sans-serif;font-size:15px;font-weight:600;">Reset Password →</a>
  </td></tr>
</table>

<p style="margin:0 0 16px;font-size:13px;color:rgba(240,237,232,0.4);line-height:1.7;">
  Or copy and paste this link into your browser:<br/>
  <a href="{$link}" style="color:#c5a572;word-break:break-all;">{$link}</a>
</p>

<p style="margin:0;font-size:13px;color:rgba(240,237,232,0.35);line-height:1.7;">
  If you didn't request this, you can safely ignore this email. Your password won't change.
</p>
HTML;

    $html = email_layout($content, "Password reset link — expires in 1 hour.");
    return send_email($to, "Reset your {$site} password", $html);
}

/**
 * Send a daily check-in reminder to paid/trial users who haven't opened
 * the app today (no chat_session row for today and no mood_log for today).
 *
 * Called from cron once per day. Returns the count of emails sent.
 * Only sends to users who have completed onboarding (have a coach_profile).
 */
function send_checkin_reminder_emails(): int {
    $site    = get_setting('site_name', 'Solen');
    $siteUrl = rtrim(get_setting('canonical_url') ?: SITE_URL, '/');
    $today   = date('Y-m-d');

    // Target: active users (free trial or paid) with a coach profile
    // who have NO chat session AND no mood log today.
    // Also fetch notification_prefs so we can respect per-user opt-outs.
    $users = db_query(
        "SELECT u.id, u.name, u.email, u.plan, cp.coach_name, cp.day_streak,
                cp.purpose, cp.notification_prefs
           FROM users u
           JOIN coach_profiles cp ON cp.user_id = u.id
          WHERE u.role != 'admin'
            AND u.plan IN ('free','pro','premium')
            AND (u.trial_ends IS NULL OR u.trial_ends >= ?)
            AND NOT EXISTS (
                SELECT 1 FROM chat_sessions cs
                 WHERE cs.user_id = u.id AND cs.session_date = ?
            )
            AND NOT EXISTS (
                SELECT 1 FROM mood_logs ml
                 WHERE ml.user_id = u.id AND ml.logged_date = ?
            )",
        [$today . ' 00:00:00', $today, $today]
    );

    $purposeLines = [
        'emotional' => "Your coach is here to listen — no agenda, just presence.",
        'anxiety'   => "A few minutes with your coach can help ground your day.",
        'growth'    => "Even a short reflection moves you forward.",
        'social'    => "Check in — your coach has a thought for you today.",
    ];

    $sent = 0;
    foreach ($users as $user) {
        // Respect per-user notification preferences
        $prefs = json_decode($user['notification_prefs'] ?? '{}', true);
        if (isset($prefs['checkin_reminder']) && $prefs['checkin_reminder'] === '0') {
            continue; // user explicitly opted out
        }

        $first     = explode(' ', trim($user['name']))[0];
        $coach     = $user['coach_name'] ?: $site;
        $streak    = (int)$user['day_streak'];
        $streakLine = $streak > 1
            ? "<p style=\"margin:0 0 16px;font-size:14px;color:rgba(240,237,232,0.45);\">🔥 You're on a {$streak}-day streak — keep it going.</p>"
            : '';
        $purposeLine = $purposeLines[$user['purpose'] ?? 'emotional'] ?? $purposeLines['emotional'];

        $content = <<<HTML
<h1 style="margin:0 0 6px;font-family:'Georgia',serif;font-size:26px;font-weight:400;color:#f0ede8;">Hey {$first}.</h1>
<p style="margin:0 0 20px;font-size:14px;color:rgba(240,237,232,0.4);">A note from {$coach}.</p>

{$streakLine}

<p style="margin:0 0 24px;font-size:16px;color:#f0ede8;line-height:1.75;">
  {$purposeLine} How are you doing today?
</p>

<table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
  <tr><td align="center">
    <a href="{$siteUrl}/app.php" style="display:inline-block;background:#c5a572;color:#1a1008;padding:13px 36px;border-radius:50px;text-decoration:none;font-family:Arial,sans-serif;font-size:15px;font-weight:600;">Check in with {$coach} →</a>
  </td></tr>
</table>

<p style="margin:0;font-size:12px;color:rgba(240,237,232,0.25);line-height:1.7;text-align:center;">
  To stop these reminders, <a href="{$siteUrl}/settings.php" style="color:rgba(240,237,232,0.35);">update your notification preferences</a>.
</p>
HTML;

        $html = email_layout($content, "{$coach} is here whenever you're ready.");
        if (send_email($user['email'], "{$coach} is thinking of you 🌿", $html)) {
            $sent++;
        }
    }
    return $sent;
}


// ── AUDIT LOGGING (Phase 1 Security) ──────────────────────────────────────
/**
 * Write an entry to the audit_log table.
 * @param int|null $userId  null for unauthenticated actions
 * @param string   $action  e.g. 'login', 'login_fail', 'register', 'crisis_detected'
 * @param string   $detail  optional extra context
 */
function audit_log(?int $userId, string $action, string $detail = ''): void {
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    try {
        db_run("INSERT INTO audit_log (user_id, action, detail, ip) VALUES (?, ?, ?, ?)", [$userId, $action, $detail, substr($ip, 0, 45)]);
    } catch (Throwable $e) {
        error_log("audit_log failed: " . $e->getMessage());
    }
}

function redirect(string $url): void {
    // Allow only relative paths (starting with /) or same-origin absolute URLs.
    // Anything else — including protocol-relative //evil.com — falls back to /app.php.
    $parsed = parse_url($url);
    if (isset($parsed['host']) || isset($parsed['scheme'])) {
        $allowedHost = parse_url(SITE_URL, PHP_URL_HOST);
        if (($parsed['host'] ?? '') !== $allowedHost) {
            $url = '/app.php'; // safe fallback
        }
    } elseif (!str_starts_with($url, '/')) {
        // Relative paths without a leading slash are also rejected
        $url = '/app.php';
    }
    header('Location: ' . $url, true, 302);
    exit;
}

/**
 * Global maintenance mode check.
 * Redirects or exits if the system is in maintenance mode and user is not an admin.
 */
function check_maintenance(bool $isJson = false): void {
    if (get_setting('maintenance_mode') === '1' && !is_admin()) {
        $msg = get_setting('maintenance_message', "We'll be back shortly.");
        if ($isJson) {
            http_response_code(503);
            echo json_encode(['ok' => false, 'error' => $msg]);
            exit;
        }
        http_response_code(503);
        echo "<!DOCTYPE html><html><head><title>Maintenance</title>
        <style>body{background:#080810;color:#f0ede8;font-family:'Georgia',serif;display:flex;align-items:center;justify-content:center;min-height:100vh;text-align:center}
        h1{font-size:48px;color:#c5a572;margin-bottom:16px}p{opacity:0.6;font-size:16px}</style></head>
        <body><div><h1>Solen</h1><p>" . h($msg) . "</p></div></body></html>";
        exit;
    }
}

function flash(string $type, string $msg): void { $_SESSION['flash'] = compact('type', 'msg'); }

function get_flash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function render_flash(): string {
    $f = get_flash();
    if (!$f) return '';
    $colors = ['success' => '#22c55e', 'error' => '#ef4444', 'info' => '#60a5fa', 'warning' => '#f59e0b'];
    $c = $colors[$f['type']] ?? '#60a5fa';
    return "<div style='background:rgba(".hexToRgb($c).",0.12);border:1px solid rgba(".hexToRgb($c).",0.3);color:#fff;padding:12px 18px;border-radius:10px;margin-bottom:20px;font-size:14px;'>{$f['msg']}</div>";
}

/**
 * Encrypt sensitive data using AES-256-CBC.
 */
function encrypt_data(string $data, ?string $key = null): string {
    $key = $key ?: (get_setting('app_key') ?: (defined('APP_KEY') ? APP_KEY : 'default-solen-key-v1-2026'));
    if (!$data) return '';
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
    return base64_encode($iv . $encrypted);
}

/**
 * Decrypt data encrypted via encrypt_data.
 */
function decrypt_data(string $payload, ?string $key = null): string {
    if (!$payload) return '';
    $key = $key ?: (get_setting('app_key') ?: (defined('APP_KEY') ? APP_KEY : 'default-solen-key-v1-2026'));
    $decoded = base64_decode($payload);
    $iv = substr($decoded, 0, 16);
    $encrypted = substr($decoded, 16);
    if (strlen($iv) < 16) return $payload; // Not encrypted
    $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    return $decrypted ?: $payload;
}

function hexToRgb(string $hex): string {
    $hex = ltrim($hex, '#');
    return implode(',', [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))]);
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function slugify(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

function time_ago(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff/60) . 'm ago';
    if ($diff < 86400)  return floor($diff/3600) . 'h ago';
    if ($diff < 604800) return floor($diff/86400) . 'd ago';
    return date('M j, Y', strtotime($datetime));
}

function format_date(string $datetime, string $format = 'M j, Y'): string {
    return date($format, strtotime($datetime));
}

function paginate(int $total, int $page, int $per = 20): array {
    $pages = max(1, ceil($total / $per));
    return ['total' => $total, 'page' => $page, 'pages' => $pages, 'per' => $per, 'offset' => ($page-1)*$per];
}

function plan_badge(string $plan): string {
    $map = ['free' => ['#94a3b8','Free'], 'pro' => ['#c5a572','Pro'], 'premium' => ['#e8c97a','Premium'], 'trial' => ['#60a5fa','Trial']];
    [$c, $l] = $map[$plan] ?? ['#94a3b8', ucfirst($plan)];
    return "<span style='background:rgba(".hexToRgb($c).",0.15);color:$c;padding:3px 10px;border-radius:50px;font-size:11px;font-weight:500;letter-spacing:0.05em'>$l</span>";
}

function status_badge(string $status): string {
    $map = ['active'=>['#22c55e','Active'], 'trial'=>['#60a5fa','Trial'], 'cancelled'=>['#ef4444','Cancelled'], 'expired'=>['#94a3b8','Expired'], 'draft'=>['#94a3b8','Draft'], 'published'=>['#22c55e','Published']];
    [$c, $l] = $map[$status] ?? ['#94a3b8', ucfirst($status)];
    return "<span style='background:rgba(".hexToRgb($c).",0.15);color:$c;padding:3px 10px;border-radius:50px;font-size:11px;font-weight:500'>$l</span>";
}

function excerpt(string $html, int $len = 160): string {
    return mb_substr(strip_tags($html), 0, $len) . '...';
}

/**
 * Activate a paid plan for a user (shared by Webhooks and manual verification).
 */
function activate_plan(int $userId, string $plan, string $billing, float $amount, string $gateway, string $ref): void {
    // Idempotency: if this exact transaction reference was already processed, skip.
    $key = "{$gateway}:{$ref}";
    if (db_one("SELECT id FROM subscriptions WHERE user_id=? AND notes=?", [$userId, $key])) {
        return;
    }

    $days    = $billing === 'yearly' ? 365 : 31;
    $expires = date('Y-m-d H:i:s', strtotime("+{$days} days"));

    get_db()->prepare("UPDATE users SET plan=? WHERE id=?")->execute([$plan, $userId]);

    // Cancel any previously active subscription
    get_db()->prepare(
        "UPDATE subscriptions SET status='cancelled', cancelled_at=datetime('now')
          WHERE user_id=? AND status='active'"
    )->execute([$userId]);

    db_run(
        "INSERT INTO subscriptions (user_id,plan,status,amount,billing_cycle,expires_at,notes)
         VALUES (?,?,'active',?,?,?,?)",
        [$userId, $plan, $amount, $billing, $expires, $key]
    );
    
    // Also update the payment_transactions table if it exists
    db_run("UPDATE payment_transactions SET status='completed' WHERE user_id=? AND tx_ref=?", [$userId, $ref]);
}
