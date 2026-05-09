<?php
/**
 * includes/image_utils.php — Media & Image Management
 */

/**
 * Download a remote image and save it locally.
 * Returns the relative path to the saved image or null on failure.
 */
function save_remote_image(string $url, string $folder = 'uploads/blog'): ?string {
    $dir = dirname(__DIR__) . '/' . $folder;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $ext = 'png'; // DALL-E default
    $filename = uniqid('img_', true) . '.' . $ext;
    $path = $dir . '/' . $filename;

    $ch = curl_init($url);
    $fp = fopen($path, 'wb');
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $ok = curl_exec($ch);
    curl_close($ch);
    fclose($fp);

    if (!$ok) {
        if (file_exists($path)) unlink($path);
        return null;
    }

    return '/' . $folder . '/' . $filename;
}
