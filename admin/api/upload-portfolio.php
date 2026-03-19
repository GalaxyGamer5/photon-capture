<?php
session_start();
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



if (!isset($_POST['category']) || !isset($_FILES['photos'])) {
    echo json_encode(['success' => false, 'error' => 'Missing category or photos']);
    exit;
}

$category = $_POST['category'];
$allowedCategories = ['portrait', 'event', 'pet'];
if (!in_array($category, $allowedCategories)) {
    echo json_encode(['success' => false, 'error' => 'Invalid category']);
    exit;
}

$targetDir = '../../assets/portfolio/';
if (!file_exists($targetDir)) {
    mkdir($targetDir, 0755, true);
}

$portfolioFile = '../../data/portfolio.json';
$portfolioData = ['images' => []];
if (file_exists($portfolioFile)) {
    $portfolioData = json_decode(file_get_contents($portfolioFile), true);
}

$uploadedCount = 0;
$errors = [];
$files = $_FILES['photos'];
$fileCount = is_array($files['name']) ? count($files['name']) : 1;

for ($i = 0; $i < $fileCount; $i++) {
    $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];
    $tmp_name = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
    $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];

    if ($error !== UPLOAD_ERR_OK) {
        $errors[] = "File {$name}: Upload error";
        continue;
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $tmp_name);
    finfo_close($finfo);
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
    if (!in_array($mimeType, $allowedTypes)) {
        $errors[] = "File {$name}: Invalid type (only JPG/PNG/WEBP allowed)";
        continue;
    }
    
    $ext = 'jpg';
    if ($mimeType === 'image/png') $ext = 'png';
    if ($mimeType === 'image/webp') $ext = 'webp';
    
    $id = uniqid();
    $newFilename = $id . '_' . time() . '.' . $ext;
    $targetPath = $targetDir . $newFilename;
    
    if (compressAndResizeImage($tmp_name, $targetPath, $mimeType)) {
        $uploadedCount++;
        // Add to JSON
        $portfolioData['images'][] = [
            'id' => $id,
            'filename' => $newFilename,
            'category' => $category,
            'date' => date('Y-m-d H:i:s')
        ];
    } else {
        $errors[] = "File {$name}: Failed to compress or save";
    }
}

if ($uploadedCount > 0) {
    file_put_contents($portfolioFile, json_encode($portfolioData, JSON_PRETTY_PRINT));
}

echo json_encode([
    'success' => true,
    'uploaded' => $uploadedCount,
    'errors' => $errors
]);
