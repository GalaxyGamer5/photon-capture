<?php
// gallery/api/get-image.php
session_start();

// Basic security check
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/../../includes/db.php';

$user = $_SESSION['user'];
$requested_folder = isset($_GET['f']) ? (string)$_GET['f'] : '';
$requested_image = isset($_GET['i']) ? (string)$_GET['i'] : '';
$thumbnail_mode = isset($_GET['thumb']) && $_GET['thumb'] === '1';

// Verify access: user's primary folder or one of their galleries
$allowed = ($requested_folder === ($user['folder'] ?? ''));
$galleryProtection = null;

try {
    $db = getDB();
    if (!$allowed && isset($user['id'])) {
        $stmt = $db->prepare("SELECT is_protected FROM galleries WHERE user_id = ? AND folder = ? LIMIT 1");
        $stmt->execute([(int)$user['id'], $requested_folder]);
        $row = $stmt->fetch();
        if ($row !== false) {
            $allowed = true;
            $galleryProtection = (bool)$row['is_protected'];
        }
    } elseif ($allowed && isset($user['id'])) {
        $stmt = $db->prepare("SELECT is_protected FROM galleries WHERE user_id = ? AND folder = ? LIMIT 1");
        $stmt->execute([(int)$user['id'], $requested_folder]);
        $val = $stmt->fetchColumn();
        if ($val !== false) {
            $galleryProtection = (bool)$val;
        }
    }
} catch (Exception $e) {}

if (!$allowed) {
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

// Check protection status (from gallery record, session, or DB)
if ($galleryProtection !== null) {
    $isProtected = $galleryProtection;
} else {
    $isProtected = isset($user['isProtected']) ? (bool)$user['isProtected'] : false;
}

// --- Fast Path Cached Thumbnail ---
if ($thumbnail_mode) {
    $thumbsDir = dirname($image_path) . '/_thumbs';
    $cachePrefix = $isProtected ? 'prot_v3_' : 'clean_';
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
    // 1. Subtle, gentle anti-AI blur applied to the photo FIRST
    // Softens micro-textures (hair, skin pores, fabric grain) so AI inpainting/watermark-removal
    // cannot hallucinate a sharp printable image, while faces, expressions, and colors remain clear.
    if (function_exists('imagefilter')) {
        $blurPasses = $thumbnail_mode ? 1 : 2;
        for ($b = 0; $b < $blurPasses; $b++) {
            imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);
        }
    }

    // Ensure alpha blending is on for TrueColor images
    imagealphablending($image, true);
    imagesavealpha($image, true);

    $width = imagesx($image);
    $height = imagesy($image);

    $fontFile = __DIR__ . '/../../includes/fonts/watermark-font.ttf';
    $hasTTF = function_exists('imagettftext') && file_exists($fontFile);

    if ($hasTTF) {
        // --- 1. Elegant Repeating Diagonal Watermark Grid ---
        $angle = 30;
        $size = max(13, (int)round($width * 0.024));
        $subSize = max(8, (int)round($size * 0.55));

        // Soft translucent colors (visible on both dark & light backgrounds)
        $white = imagecolorallocatealpha($image, 255, 255, 255, 68); // ~46% opacity
        $shadow = imagecolorallocatealpha($image, 0, 0, 0, 96);       // subtle dark shadow

        $text = "PHOTON CAPTURE";
        $sub  = "VORSCHAU  ·  PROOF";

        $stepX = max(180, (int)round($width * 0.32));
        $stepY = max(120, (int)round($height * 0.28));

        for ($y = -200; $y < $height + 300; $y += $stepY) {
            $row = (int)floor($y / $stepY);
            $shiftX = ($row % 2 === 0) ? 0 : (int)($stepX / 2);
            for ($x = -200 + $shiftX; $x < $width + 300; $x += $stepX) {
                // Drop shadow
                @imagettftext($image, $size, $angle, (int)($x + 2), (int)($y + 2), $shadow, $fontFile, $text);
                // Main text
                @imagettftext($image, $size, $angle, (int)$x, (int)$y, $white, $fontFile, $text);

                // Subtext angled below
                $rad = deg2rad($angle + 90);
                $subDist = (int)round($size * 1.3);
                $sx = (int)round($x + cos($rad) * $subDist);
                $sy = (int)round($y - sin($rad) * $subDist);
                @imagettftext($image, $subSize, $angle, (int)($sx + 1), (int)($sy + 1), $shadow, $fontFile, $sub);
                @imagettftext($image, $subSize, $angle, (int)$sx, (int)$sy, $white, $fontFile, $sub);
            }
        }

        // --- 2. Prominent Center Brand Seal ---
        $centerSize = max(18, (int)round($width * 0.046));
        $cWhite  = imagecolorallocatealpha($image, 255, 255, 255, 45); // ~65% opacity
        $cShadow = imagecolorallocatealpha($image, 0, 0, 0, 85);

        $mainText = "PHOTON CAPTURE";
        $box = @imagettfbbox($centerSize, 0, $fontFile, $mainText);
        if ($box) {
            $textW = abs($box[4] - $box[0]);
            $textH = abs($box[5] - $box[1]);
            $cx = (int)(($width - $textW) / 2);
            $cy = (int)(($height + $textH) / 2);

            @imagettftext($image, $centerSize, 0, $cx + 2, $cy + 2, $cShadow, $fontFile, $mainText);
            @imagettftext($image, $centerSize, 0, $cx, $cy, $cWhite, $fontFile, $mainText);

            // Subtitle
            $subCenterSize = max(10, (int)round($centerSize * 0.40));
            $subText = "VORSCHAU  ·  NICHT FREIGEGEBEN";
            $sbox = @imagettfbbox($subCenterSize, 0, $fontFile, $subText);
            if ($sbox) {
                $stextW = abs($sbox[4] - $sbox[0]);
                $scx = (int)(($width - $stextW) / 2);
                $scy = $cy + (int)round($centerSize * 0.85);

                @imagettftext($image, $subCenterSize, 0, $scx + 1, $scy + 1, $cShadow, $fontFile, $subText);
                @imagettftext($image, $subCenterSize, 0, $scx, $scy, $cWhite, $fontFile, $subText);
            }
        }
    } else {
        // Fallback: clean non-pixelated bitmap pattern
        $color_main = imagecolorallocatealpha($image, 255, 255, 255, 55);
        $color_dark = imagecolorallocatealpha($image, 0, 0, 0, 85);
        $text = "PHOTON CAPTURE - VORSCHAU";
        $fontSize = 5;
        $stepX = 260;
        $stepY = 160;
        for ($y = 40; $y < $height; $y += $stepY) {
            for ($x = 40; $x < $width; $x += $stepX) {
                imagestring($image, $fontSize, (int)($x + 1), (int)($y + 1), $text, $color_dark);
                imagestring($image, $fontSize, (int)$x, (int)$y, $text, $color_main);
            }
        }
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
