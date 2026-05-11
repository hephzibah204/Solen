<?php
function test_login($email, $password, $expected_redirect) {
    $url = 'http://localhost:8000/login.php';
    $cookie_file = tempnam(sys_get_temp_dir(), 'solen_cookie_');

    // 1. Get CSRF token and session cookie
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
    $response = curl_exec($ch);
    
    preg_match('/name="csrf" value="([^"]+)"/', $response, $matches);
    $csrf = $matches[1] ?? '';

    if (!$csrf) {
        return "Failed to get CSRF token for $email";
    }

    // 2. Perform Login
    $post_data = http_build_query([
        'email' => $email,
        'password' => $password,
        'csrf' => $csrf
    ]);

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_HEADER, true);
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    $location = '';
    if (preg_match('/Location: (.*)/i', $response, $matches)) {
        $location = trim($matches[1]);
    }

    unlink($cookie_file);

    if (strpos($location, $expected_redirect) !== false) {
        return "SUCCESS: $email logged in and redirected to $location";
    } else {
        return "FAILURE: $email failed to log in. Location: $location. Response contains error: " . (strpos($response, 'error-msg') !== false ? 'Yes' : 'No');
    }
}

echo "Testing Admin Login...\n";
echo test_login('admin@getsolen.com', 'WaitOnGod2026', '/admin/index.php') . "\n\n";

echo "Testing User Login...\n";
echo test_login('testuser@example.com', 'Password123!', '/app.php') . "\n";
