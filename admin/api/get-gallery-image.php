<?php
// admin/api/get-gallery-image.php
session_start();

// Basic security check for admin
// Note: our current admin auth is client-side sessionStorage for simplicity,
// but for an image fetching script, we can't easily read sessionStorage.
// As a fallback, we just ensure the path requested is strictly within gallery/assets.

$requested_folder = isset($_GET['f']) ? $_GET['f'] : '';
$requested_image = isset($_GET['i']) ? $_GET['i'] : '';
$thumbnail_mode  = isset($_GET['thumb']) && $_GET['thumb'] === '1';

// Decode any URL encoding
$requested_folder = urldecode($requested_folder);
$requested_image = urldecode($requested_image);

// Sanitize inputs
$requested_folder = basename($requested_folder);
$requested_image = basename($requested_image);

if (empty($requested_folder) || empty($requested_image)) {
    http_response_code(400);
    exit('Missing parameters');
}

$base_dir = __DIR__ . '/../../gallery/assets/' . $requested_folder;
$image_path = $base_dir . '/' . $requested_image;

if (!file_exists($image_path)) {
    // Fallback if running from flattened /api/ directory
    $base_dir = __DIR__ . '/../gallery/assets/' . $requested_folder;
    $image_path = $base_dir . '/' . $requested_image;
}

if (!file_exists($image_path)) {
    http_response_code(404);
    exit('Image not found');
}

// ── Thumbnail handling ──────────────────────────────────────────────────────
if ($thumbnail_mode && function_exists('imagecreatefromjpeg')) {
    $thumbsDir = $base_dir . '/_thumbs';
    $thumbPath  = $thumbsDir . '/' . $requested_image;

    // Serve cached thumbnail if it exists and is newer than the source
    if (file_exists($thumbPath) && filemtime($thumbPath) >= filemtime($image_path)) {
        header('Cache-Control: private, max-age=604800'); // 7-day cache for thumbs
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . filesize($thumbPath));
        header('X-Image-Delivered-By: admin-api-thumb-cached');
        readfile($thumbPath);
        exit;
    }

    // Determine source MIME
    $info = @getimagesize($image_path);
    $mime = $info['mime'] ?? 'image/jpeg';

    $src = null;
    if ($mime === 'image/jpeg')  $src = @imagecreatefromjpeg($image_path);
    elseif ($mime === 'image/png')  $src = @imagecreatefrompng($image_path);
    elseif ($mime === 'image/webp') $src = @imagecreatefromwebp($image_path);

    if ($src) {
        $origW = imagesx($src);
        $origH = imagesy($src);
        $thumbW = 400;
        $thumbH = (int)round($origH * $thumbW / $origW);

        $thumb = imagecreatetruecolor($thumbW, $thumbH);
        imagecopyresampled($thumb, $src, 0, 0, 0, 0, $thumbW, $thumbH, $origW, $origH);

        // Cache to disk (create dir if needed)
        if (!is_dir($thumbsDir)) {
            @mkdir($thumbsDir, 0755, true);
        }
        if (is_dir($thumbsDir) && is_writable($thumbsDir)) {
            imagejpeg($thumb, $thumbPath, 70);
        }

        header('Cache-Control: private, max-age=604800');
        header('Content-Type: image/jpeg');
        header('X-Image-Delivered-By: admin-api-thumb-fresh');
        imagejpeg($thumb, null, 70);
        exit;
    }
    // If GD failed for any reason, fall through to serve original
}
// ── End thumbnail handling ──────────────────────────────────────────────────

// Determine MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = @finfo_file($finfo, $image_path);
finfo_close($finfo);

if ($mimeType === false) {
    if (preg_match('/\.png$/i', $requested_image)) {
        $mimeType = 'image/png';
    } else {
        $mimeType = 'image/jpeg';
    }
}

// Caching headers
header('Cache-Control: private, max-age=86400'); // 1 day cache
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($image_path));
header('X-Image-Delivered-By: admin-api'); // Diagnostic header

// Output file directly
readfile($image_path);
?>
