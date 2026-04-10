<?php
// admin/api/delete-user-folder.php
session_start();

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['folder'])) {
    echo json_encode(['success' => false, 'message' => 'No folder specified']);
    exit;
}

// SECURE THE INPUT: Remove any attempt to navigate up directories (e.g. ../../)
$folder = basename($input['folder']);

if (empty($folder) || $folder === '.' || $folder === '..') {
    echo json_encode(['success' => false, 'message' => 'Invalid folder name']);
    exit;
}

$targetDir = __DIR__ . '/../../gallery/assets/' . $folder;

// Recursive directory deletion function
function deleteDirectory($dir) {
    if (!file_exists($dir)) {
        return true;
    }
    if (!is_dir($dir)) {
        return unlink($dir);
    }
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }
    return rmdir($dir);
}

// Execute the deletion safely
if (file_exists($targetDir) && is_dir($targetDir)) {
    if (deleteDirectory($targetDir)) {
        echo json_encode(['success' => true, 'message' => 'Folder and files deleted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete some files']);
    }
} else {
    // If the folder didn't exist anyway, consider it a success
    echo json_encode(['success' => true, 'message' => 'Folder already did not exist']);
}
