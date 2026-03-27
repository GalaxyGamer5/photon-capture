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

// Ensure user only accesses their own folder
error_log("Watermark debug: Request folder=$requested_folder, image=$requested_image, user=" . ($user['username'] ?? 'NONE'));
if ($requested_folder !== $user['folder']) {
    http_response_code(403);
    exit('Access Denied');
}

// Sanitize filename to prevent directory traversal
$requested_image = basename($requested_image);
$image_path = __DIR__ . '/../assets/' . $requested_folder . '/' . $requested_image;

if (!file_exists($image_path)) {
    http_response_code(404);
    exit('Image not found');
}

// Load users database to check protection status
$usersFile = __DIR__ . '/../data/users.js';
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

if ($isProtected) {
    // Ensure alpha blending is on for TrueColor images
    imagealphablending($image, true);

    // 1. Apply more aggressive blur
    if (function_exists('imagefilter')) {
        for ($i = 0; $i < 6; $i++) { // Increased passes from 3 to 6
            imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);
        }
        // Add a bit of pixelation for extra protection
        imagefilter($image, IMG_FILTER_PIXELATE, 2, true);
    }

    // 2. Apply Watermark Overlay
    $width = imagesx($image);
    $height = imagesy($image);
    
    // Create colors - Increased opacity (lower alpha value = more opaque)
    $color_main = imagecolorallocatealpha($image, 255, 255, 255, 30); // Very opaque white (30 instead of 60)
    $color_black = imagecolorallocatealpha($image, 0, 0, 0, 50);    // Very opaque black (50 instead of 80)
    
    $text = "PHOTON-CAPTURE";
    $fontSize = 5; 
    
    $textWidth = imagefontwidth($fontSize) * strlen($text);
    $textHeight = imagefontheight($fontSize);
    
    // Dense diagonal grid
    $spacing_x = 250;
    $spacing_y = 150;
    
    for ($y = -200; $y < $height + 200; $y += $spacing_y) {
        $offset_x = (int)(($y / $spacing_y) % 2 == 0 ? 0 : $spacing_x / 2);
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
    imagefilledrectangle($image, 0, $bannerY - 40, $bannerFullWidth, $bannerY + 40, $color_black);
    
    // Draw the banner text multiple times for bold effect and extra visibility
    $textX = (int)(($width - $bannerWidth) / 2);
    $textY = (int)($bannerY - 8);
    
    for($o = -2; $o <= 2; $o++) {
        imagestring($image, $bannerFontSize, $textX + $o, $textY, $bannerText, $color_main);
        imagestring($image, $bannerFontSize, $textX, $textY + $o, $bannerText, $color_main);
    }
}

// Serve the image
header('Content-Type: image/jpeg'); 
header('Cache-Control: public, max-age=3600'); 

if ($isProtected) {
    imagejpeg($image, null, 75); 
} else {
    imagejpeg($image, null, 90); 
}

imagedestroy($image);
?>
