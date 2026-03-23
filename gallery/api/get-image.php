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
$info = getimagesize($image_path);
$mime = $info['mime'];

// Support only common formats for watermark
if ($mime === 'image/jpeg') {
    $image = imagecreatefromjpeg($image_path);
} elseif ($mime === 'image/png') {
    $image = imagecreatefrompng($image_path);
} elseif ($mime === 'image/webp') {
    $image = imagecreatefromwebp($image_path);
} else {
    // Unsupported format for processing, serve raw
    header('Content-Type: ' . $mime);
    readfile($image_path);
    exit;
}

if ($isProtected && $image) {
    // 1. Apply slight blur
    // Gaussian blur done 3 times for a "noticeable but viewable" effect
    for ($i = 0; $i < 3; $i++) {
        imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);
    }

    // 2. Apply Watermark Overlay
    $width = imagesx($image);
    $height = imagesy($image);
    
    // Create a semi-transparent color for the watermark
    $white = imagecolorallocatealpha($image, 255, 255, 255, 80); // 80 is roughly 40% transparency
    
    // Draw repeating "PHOTON CAPTURE" text
    $text = "PHOTON-CAPTURE";
    $fontSize = 5; // Built-in GD font size (1-5)
    
    $textWidth = imagefontwidth($fontSize) * strlen($text);
    $textHeight = imagefontheight($fontSize);
    
    // Grid of watermarks
    for ($x = 50; $x < $width; $x += $textWidth + 200) {
        for ($y = 50; $y < $height; $y += 200) {
            imagestring($image, $fontSize, $x, $y, $text, $white);
        }
    }
    
    // Also a big one in the center
    $centerText = "COPYRIGHT PHOTON-CAPTURE - PREVIEW ONLY";
    $centerTextWidth = imagefontwidth($fontSize) * strlen($centerText);
    imagestring($image, $fontSize, ($width - $centerTextWidth) / 2, $height / 2, $centerText, $white);
}

// Serve the image
header('Content-Type: image/jpeg'); // Output as JPEG for consistency
header('Cache-Control: public, max-age=3600'); // Cache for 1 hour

if ($isProtected) {
    imagejpeg($image, null, 75); // Lower quality for previews
} else {
    imagejpeg($image, null, 95); // High quality for unlocked
}

imagedestroy($image);
?>
