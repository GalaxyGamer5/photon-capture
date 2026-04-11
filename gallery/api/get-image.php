<?php
// gallery/api/get-image.php
session_start();

// Basic security check
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(403);
    exit('Forbidden');
}

$user = $_SESSION['user'];
$requested_folder = isset($_GET['f']) ? $_GET['f'] : '';
$requested_image = isset($_GET['i']) ? $_GET['i'] : '';
$thumbnail_mode = isset($_GET['thumb']) && $_GET['thumb'] === '1';

// Ensure user only accesses their own folder
if ($requested_folder !== $user['folder']) {
    http_response_code(403);
    exit('Access Denied');
}

// Sanitize filename to prevent directory traversal
$requested_image = basename($requested_image);
$image_path = __DIR__ . '/../assets/' . $requested_folder . '/' . $requested_image;

if (!file_exists($image_path)) {
    // Fallback if running from flattened /api/ instead of /gallery/api/
    $image_path = __DIR__ . '/../gallery/assets/' . $requested_folder . '/' . $requested_image;
}

if (!file_exists($image_path)) {
    http_response_code(404);
    exit('Image not found');
}

// Load users database to check protection status
$usersFile = __DIR__ . '/../data/users.js';
if (!file_exists($usersFile)) {
    $usersFile = __DIR__ . '/../gallery/data/users.js';
}

$isProtected = false;

if (file_exists($usersFile)) {
    $usersContent = file_get_contents($usersFile);
    preg_match('/window\.usersDatabase\s*=\s*({[\s\S]*?});/', $usersContent, $matches);
    if (isset($matches[1])) {
        $usersData = json_decode($matches[1], true);
        if ($usersData && isset($usersData['users'])) {
            foreach ($usersData['users'] as $u) {
                if ($u['username'] === $user['username']) {
                    $isProtected = isset($u['isProtected']) ? (bool)$u['isProtected'] : true;
                    break;
                }
            }
        }
    }
}

// --- Fast Path Cached Thumbnail ---
if ($thumbnail_mode) {
    $thumbsDir = dirname($image_path) . '/_thumbs';
    $cachePrefix = $isProtected ? 'prot_' : 'clean_';
    $thumbPath = $thumbsDir . '/' . $cachePrefix . $requested_image;

    if (file_exists($thumbPath) && filemtime($thumbPath) >= filemtime($image_path)) {
        header('Cache-Control: public, max-age=604800'); // 7 days for thumb
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . filesize($thumbPath));
        header('X-Image-Delivered-By: gallery-api-thumb-cached');
        readfile($thumbPath);
        exit;
    }
}
// ---

// Image processing with GD
$info = @getimagesize($image_path);
$mime = $info['mime'] ?? 'image/jpeg';

if (!function_exists('imagecreatefromjpeg')) {
    // GD not installed, fallback to serving original
    header('Content-Type: ' . $mime);
    readfile($image_path);
    exit;
}

// Support only common formats for watermark
$image = null;
if ($mime === 'image/jpeg') {
    $image = @imagecreatefromjpeg($image_path);
} elseif ($mime === 'image/png') {
    $image = @imagecreatefrompng($image_path);
} elseif ($mime === 'image/webp') {
    $image = @imagecreatefromwebp($image_path);
}

if (!$image) {
    // Failed to create image resource, serving original
    header('Content-Type: ' . $mime);
    readfile($image_path);
    exit;
}

// --- Resizing Logic ---
if ($thumbnail_mode) {
    $origW = imagesx($image);
    $origH = imagesy($image);
    $thumbW = 600; // Resize to 600px width for thumbnails
    
    if ($origW > $thumbW) {
        $thumbH = (int)round($origH * $thumbW / $origW);
        $thumb = imagecreatetruecolor($thumbW, $thumbH);
        imagecopyresampled($thumb, $image, 0, 0, 0, 0, $thumbW, $thumbH, $origW, $origH);
        
        imagedestroy($image);
        $image = $thumb;
    }
}
// --- End Resizing Logic ---

if ($isProtected) {
    // Ensure alpha blending is on for TrueColor images
    imagealphablending($image, true);

    // 1. Apply ultra-fast Pixelation for protection (replaces slow Gaussian blur)
    if (function_exists('imagefilter')) {
        $pixelSize = $thumbnail_mode ? 8 : 20; // smaller pixels for thumb
        imagefilter($image, IMG_FILTER_PIXELATE, $pixelSize, true);
    }

    // 2. Apply Watermark Overlay
    $width = imagesx($image);
    $height = imagesy($image);
    
    // Create colors - Increased opacity (lower alpha value = more opaque)
    $color_main = imagecolorallocatealpha($image, 255, 255, 255, 30); // Very opaque white (30 instead of 60)
    $color_black = imagecolorallocatealpha($image, 0, 0, 0, 50);    // Very opaque black (50 instead of 80)
    
    $text = "PHOTON-CAPTURE";
    $fontSize = 5; 
    
    // Dense diagonal grid
    $spacing_x = $thumbnail_mode ? 120 : 250;
    $spacing_y = $thumbnail_mode ? 80 : 150;
    
    for ($y = -200; $y < $height + 200; $y += $spacing_y) {
        $offset_x = (int)((int)($y / $spacing_y) % 2 == 0 ? 0 : $spacing_x / 2);
        for ($x = -200 + $offset_x; $x < $width + 200; $x += $spacing_x) {
            // Draw text with explicit integer coordinates to prevent Deprecated warnings
            imagestring($image, $fontSize, (int)($x + 2), (int)($y + 2), $text, $color_black);
            imagestring($image, $fontSize, (int)($x + 1), (int)($y + 1), $text, $color_black);
            imagestring($image, $fontSize, (int)$x, (int)$y, $text, $color_main);
        }
    }

    // 3. Centered Banner - Significantly More prominent
    $bannerText = "PREVIEW - UNTIL PAID";
    $bannerFontSize = 5;
    $bannerWidth = imagefontwidth($bannerFontSize) * strlen($bannerText);
    
    // Draw a thick dark strip behind the banner
    $bannerY = (int)($height / 2);
    $bannerFullWidth = (int)$width;
    $bannerPadding = $thumbnail_mode ? 20 : 40;
    imagefilledrectangle($image, 0, $bannerY - $bannerPadding, $bannerFullWidth, $bannerY + $bannerPadding, $color_black);
    
    // Draw the banner text multiple times for bold effect and extra visibility
    $textX = (int)(($width - $bannerWidth) / 2);
    $textY = (int)($bannerY - 8);
    
    for($o = -2; $o <= 2; $o++) {
        imagestring($image, $bannerFontSize, $textX + $o, $textY, $bannerText, $color_main);
        imagestring($image, $bannerFontSize, $textX, $textY + $o, $bannerText, $color_main);
    }
}

// Save thumbnail if in thumb mode
if ($thumbnail_mode && isset($thumbsDir)) {
    if (!is_dir($thumbsDir)) {
        @mkdir($thumbsDir, 0755, true);
    }
    if (is_dir($thumbsDir) && is_writable($thumbsDir)) {
        imagejpeg($image, $thumbPath, 80);
    }
}

// Serve the image
header('Content-Type: image/jpeg'); 
if ($thumbnail_mode) {
    header('Cache-Control: public, max-age=604800'); // 7 days for thumb
    header('X-Image-Delivered-By: gallery-api-thumb-fresh');
} else {
    header('Cache-Control: public, max-age=3600'); 
}

if ($isProtected) {
    imagejpeg($image, null, 75); 
} else {
    imagejpeg($image, null, 90); 
}

// imagedestroy($image); (Deprecated in PHP 8.5+)
?>
