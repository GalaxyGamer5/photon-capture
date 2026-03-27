<?php
// admin/api/get-gallery-image.php
session_start();

// Basic security check for admin
// Note: our current admin auth is client-side sessionStorage for simplicity,
// but for an image fetching script, we can't easily read sessionStorage.
// As a fallback, we just ensure the path requested is strictly within gallery/assets.

$requested_folder = isset($_GET['f']) ? $_GET['f'] : '';
$requested_image = isset($_GET['i']) ? $_GET['i'] : '';

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

$image_path = __DIR__ . '/../../gallery/assets/' . $requested_folder . '/' . $requested_image;

if (!file_exists($image_path)) {
    // Fallback if running from flattened /api/ directory
    $image_path = __DIR__ . '/../gallery/assets/' . $requested_folder . '/' . $requested_image;
}

if (!file_exists($image_path)) {
    http_response_code(404);
    exit('Image not found');
}

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
