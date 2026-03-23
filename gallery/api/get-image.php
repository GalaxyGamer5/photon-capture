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
        foreach ($usersData['users'] as $u) {
            if ($u['username'] === $user['username']) {
                $isProtected = isset($u['isProtected']) ? $u['isProtected'] : false;
                break;
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
    // 1. Apply slight blur
    if (function_exists('imagefilter')) {
        for ($i = 0; $i < 3; $i++) {
            imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);
        }
    }

    // 2. Apply Watermark Overlay
    $width = imagesx($image);
    $height = imagesy($image);
    
    // Create colors
    $color_main = imagecolorallocatealpha($image, 255, 255, 255, 95); // White semi-trans
    $color_black = imagecolorallocatealpha($image, 0, 0, 0, 110);    // Black semi-trans for contrast
    
    $text = "PHOTON-CAPTURE";
    $fontSize = 5; 
    
    $textWidth = imagefontwidth($fontSize) * strlen($text);
    $textHeight = imagefontheight($fontSize);
    
    // Dense diagonal grid of watermarks
    for ($y = -200; $y < $height + 200; $y += 200) {
        for ($x = -200; $x < $width + 200; $x += 400) {
            // Shadow for readability
            imagestring($image, $fontSize, $x + 1, $y + 1, $text, $color_black);
            imagestring($image, $fontSize, $x, $y, $text, $color_main);
        }
    }

    // Centered Banner
    $bannerText = "PREVIEW - UNTIL PAID";
    $bannerWidth = imagefontwidth($fontSize) * strlen($bannerText);
    
    // Draw a semi-transparent dark strip behind the banner
    imagefilledrectangle($image, 0, ($height/2) - 20, $width, ($height/2) + 20, $color_black);
    imagestring($image, $fontSize, ($width - $bannerWidth) / 2, ($height / 2) - 8, $bannerText, $color_main);
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
