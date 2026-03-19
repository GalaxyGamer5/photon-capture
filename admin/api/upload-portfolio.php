<?php
session_start();
header('Content-Type: application/json');

// Direct move without compression (compression happens on client side)
function moveImage($sourcePath, $destinationPath) {
    return move_uploaded_file($sourcePath, $destinationPath);
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
    if (!@mkdir($targetDir, 0775, true)) {
        echo json_encode(['success' => false, 'error' => 'Failed to create portfolio directory. Check permissions of assets/ folder.']);
        exit;
    }
}

$portfolioFile = __DIR__ . '/../../data/portfolio.json';
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
    
    $extension = 'jpg';
    $filename = uniqid('portfolio_') . '.' . $extension;
    $targetFile = $targetDir . $filename;
    
    // Convert to JPG if possible
    $tmpFile = $tmp_name; // Use the already extracted $tmp_name
    $info = getimagesize($tmpFile);
    $mime = $info['mime'];
    
    $img = null;
    if ($mime == 'image/jpeg') $img = imagecreatefromjpeg($tmpFile);
    elseif ($mime == 'image/png') $img = imagecreatefrompng($tmpFile);
    elseif ($mime == 'image/webp') $img = imagecreatefromwebp($tmpFile);
    
    if ($img) {
        if (imagejpeg($img, $targetFile, 90)) {
            $uploadedCount++;
            $portfolioData['images'][] = [
                'id' => uniqid(),
                'filename' => $filename,
                'category' => $category,
                'date' => date('Y-m-d H:i:s') // Changed to H:i:s for consistency with original
            ];
        } else {
            $errors[] = "File {$name}: Failed to save converted image"; // More specific error
        }
        imagedestroy($img);
    } else {
        // Fallback if image conversion failed or type not supported by GD
        if (move_uploaded_file($tmpFile, $targetFile)) {
            $uploadedCount++;
            $portfolioData['images'][] = [
                'id' => uniqid(),
                'filename' => $filename,
                'category' => $category,
                'date' => date('Y-m-d H:i:s') // Changed to H:i:s for consistency with original
            ];
        } else {
            $errors[] = "File {$name}: Failed to move uploaded file (fallback)"; // More specific error
        }
    }
}

if ($uploadedCount > 0) {
    file_put_contents($portfolioFile, json_encode($portfolioData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

echo json_encode([
    'success' => true,
    'uploaded' => $uploadedCount,
    'errors' => $errors
]);
