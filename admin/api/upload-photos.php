<?php
// Start session for admin auth
session_start();

// Check admin authentication


// Set JSON header
header('Content-Type: application/json');

// Direct move without compression (compression happens on client side)
function moveImage($sourcePath, $destinationPath) {
    return move_uploaded_file($sourcePath, $destinationPath);
}

// Get gallery ID from POST
if (!isset($_POST['galleryId']) || !isset($_FILES['photos'])) {
    echo json_encode(['success' => false, 'error' => 'Missing gallery ID or photos']);
    exit;
}

$galleryId = $_POST['galleryId'];

// Load users database to get folder name
$usersFile = __DIR__ . '/../../gallery/data/users.js';
if (!file_exists($usersFile)) {
    echo json_encode(['success' => false, 'error' => 'User database file missing']);
    exit;
}

$usersContent = file_get_contents($usersFile);
if (!$usersContent || !preg_match('/window\.usersDatabase\s*=\s*({[\s\S]*?});/', $usersContent, $matches)) {
    echo json_encode(['success' => false, 'error' => 'Invalid user database format']);
    exit;
}

$usersData = json_decode($matches[1], true);
if (!$usersData || !isset($usersData['users'])) {
    echo json_encode(['success' => false, 'error' => 'Failed to parse user database']);
    exit;
}

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
$targetDir = __DIR__ . '/../../gallery/assets/' . $gallery['folder'] . '/';
if (!file_exists($targetDir)) {
    if (!@mkdir($targetDir, 0775, true)) {
        echo json_encode(['success' => false, 'error' => 'Failed to create gallery directory. Check permissions.']);
        exit;
    }
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
    
    // Move uploaded file
    if (moveImage($files['tmp_name'][$i], $targetPath)) {
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
$jsContent .= "window.usersDatabase = " . json_encode($usersData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . ";\n";

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
