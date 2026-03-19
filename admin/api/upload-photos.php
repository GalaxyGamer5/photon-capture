<?php
// Start session for admin auth
session_start();

// Check admin authentication
if (!isset($_SESSION['photon_admin_auth']) || $_SESSION['photon_admin_auth'] !== 'true') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Set JSON header
header('Content-Type: application/json');

function compressAndResizeImage($sourcePath, $destinationPath, $mimeType, $maxWidth = 1920, $maxHeight = 1920, $quality = 85) {
    list($origWidth, $origHeight) = getimagesize($sourcePath);
    if (!$origWidth || !$origHeight) return false;

    // Handle EXIF orientation for JPEGs
    $image = null;
    switch ($mimeType) {
        case 'image/jpeg':
        case 'image/jpg':
            $image = imagecreatefromjpeg($sourcePath);
            if (function_exists('exif_read_data')) {
                $exif = @exif_read_data($sourcePath);
                if ($exif && isset($exif['Orientation'])) {
                    $orientation = $exif['Orientation'];
                    $deg = 0;
                    if ($orientation == 3) $deg = 180;
                    if ($orientation == 6) $deg = 270;
                    if ($orientation == 8) $deg = 90;
                    if ($deg) {
                        $image = imagerotate($image, $deg, 0);
                        if ($deg == 90 || $deg == 270) {
                            $tmp = $origWidth;
                            $origWidth = $origHeight;
                            $origHeight = $tmp;
                        }
                    }
                }
            }
            break;
        case 'image/png':
            $image = imagecreatefrompng($sourcePath);
            break;
        case 'image/webp':
            if (function_exists('imagecreatefromwebp')) {
                $image = imagecreatefromwebp($sourcePath);
            }
            break;
    }

    if (!$image) return false;

    // Calculate new dimensions respecting aspect ratio
    $ratio = $origWidth / $origHeight;
    $newWidth = $origWidth;
    $newHeight = $origHeight;

    if ($newWidth > $maxWidth) {
        $newWidth = $maxWidth;
        $newHeight = $newWidth / $ratio;
    }
    if ($newHeight > $maxHeight) {
        $newHeight = $maxHeight;
        $newWidth = $newHeight * $ratio;
    }

    // Create blank canvas
    $image_p = imagecreatetruecolor((int)$newWidth, (int)$newHeight);

    // Preserve transparency for PNG and WebP
    if ($mimeType == 'image/png' || $mimeType == 'image/webp') {
        imagealphablending($image_p, false);
        imagesavealpha($image_p, true);
        $transparent = imagecolorallocatealpha($image_p, 255, 255, 255, 127);
        imagefilledrectangle($image_p, 0, 0, (int)$newWidth, (int)$newHeight, $transparent);
    }

    // Resample
    imagecopyresampled($image_p, $image, 0, 0, 0, 0, (int)$newWidth, (int)$newHeight, $origWidth, $origHeight);

    // Save
    $success = false;
    switch ($mimeType) {
        case 'image/jpeg':
        case 'image/jpg':
            $success = imagejpeg($image_p, $destinationPath, $quality);
            break;
        case 'image/png':
            $pngQuality = round((100 - $quality) / 100 * 9);
            $success = imagepng($image_p, $destinationPath, $pngQuality);
            break;
        case 'image/webp':
            if (function_exists('imagewebp')) {
                $success = imagewebp($image_p, $destinationPath, $quality);
            }
            break;
    }

    imagedestroy($image_p);
    imagedestroy($image);

    return $success;
}

// Get gallery ID from POST
if (!isset($_POST['galleryId']) || !isset($_FILES['photos'])) {
    echo json_encode(['success' => false, 'error' => 'Missing gallery ID or photos']);
    exit;
}

$galleryId = $_POST['galleryId'];

// Load users database to get folder name
$usersFile = '../../gallery/data/users.js';
$usersContent = file_get_contents($usersFile);
preg_match('/window\.usersDatabase\s*=\s*({[\s\S]*?});/', $usersContent, $matches);
$usersData = json_decode($matches[1], true);

// Find the gallery
$gallery = null;
foreach ($usersData['users'] as &$user) {
    if ($user['id'] === $galleryId) {
        $gallery = &$user;
        break;
    }
}

if (!$gallery) {
    echo json_encode(['success' => false, 'error' => 'Gallery not found']);
    exit;
}

// Target directory
$targetDir = '../../gallery/assets/' . $gallery['folder'] . '/';
if (!file_exists($targetDir)) {
    mkdir($targetDir, 0755, true);
}

// Get current image count
$currentCount = $gallery['imageCount'] ?? 0;
$uploadedCount = 0;
$errors = [];

// Handle multiple file uploads
$files = $_FILES['photos'];
$fileCount = count($files['name']);

for ($i = 0; $i < $fileCount; $i++) {
    if ($files['error'][$i] !== UPLOAD_ERR_OK) {
        $errors[] = "File {$files['name'][$i]}: Upload error";
        continue;
    }
    
    // Validate file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $files['tmp_name'][$i]);
    finfo_close($finfo);
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    if (!in_array($mimeType, $allowedTypes)) {
        $errors[] = "File {$files['name'][$i]}: Invalid type (only JPG/PNG allowed)";
        continue;
    }
    
    // Determine file extension
    $ext = ($mimeType === 'image/png') ? 'png' : 'jpg';
    
    // New filename (auto-increment)
    $currentCount++;
    $newFilename = $currentCount . '.' . $ext;
    $targetPath = $targetDir . $newFilename;
    
    // Compress and Move uploaded file
    if (compressAndResizeImage($files['tmp_name'][$i], $targetPath, $mimeType)) {
        $uploadedCount++;
    } else {
        $errors[] = "File {$files['name'][$i]}: Failed to compress or save";
        $currentCount--; // Rollback counter
    }
}

// Update user's image count in database
$gallery['imageCount'] = $currentCount;

// Save updated users.js
$jsContent = "// Client-side user database\n";
$jsContent .= "// In a real application, this would be a server-side database\n";
$jsContent .= "window.usersDatabase = " . json_encode($usersData, JSON_PRETTY_PRINT) . ";\n";

if (file_put_contents($usersFile, $jsContent) === false) {
    echo json_encode(['success' => false, 'error' => 'Failed to update user database']);
    exit;
}

echo json_encode([
    'success' => true,
    'uploaded' => $uploadedCount,
    'newTotal' => $currentCount,
    'errors' => $errors
]);
