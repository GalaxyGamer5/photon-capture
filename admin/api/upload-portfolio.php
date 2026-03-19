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
    mkdir($targetDir, 0755, true);
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
    
    $ext = 'jpg';
    if ($mimeType === 'image/png') $ext = 'png';
    if ($mimeType === 'image/webp') $ext = 'webp';
    
    $id = uniqid();
    $newFilename = $id . '_' . time() . '.' . $ext;
    $targetPath = $targetDir . $newFilename;
    
    if (moveImage($tmp_name, $targetPath)) {
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
    file_put_contents($portfolioFile, json_encode($portfolioData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    chmod($portfolioFile, 0664);
}

echo json_encode([
    'success' => true,
    'uploaded' => $uploadedCount,
    'errors' => $errors
]);
