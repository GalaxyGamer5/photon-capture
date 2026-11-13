<?php
// gallery/api/list-images.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true || !isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

require_once __DIR__ . '/../../includes/db.php';

$user = $_SESSION['user'];
$userId = (int)$user['id'];
$folder = isset($_GET['folder']) ? trim($_GET['folder']) : ($user['folder'] ?? '');

// Security: Check if folder belongs to user
$allowed = ($folder === ($user['folder'] ?? ''));
if (!$allowed && $userId > 0) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT COUNT(*) FROM galleries WHERE user_id = ? AND folder = ?");
        $stmt->execute([$userId, $folder]);
        $allowed = ($stmt->fetchColumn() > 0);
    } catch (Exception $e) {}
}

if (!$allowed || $folder === '' || strpos($folder, '..') !== false) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

$baseDir = realpath(__DIR__ . '/../../gallery/assets');
$fullPath = $baseDir . DIRECTORY_SEPARATOR . $folder;

if (!$baseDir || !is_dir($fullPath) || strpos(realpath($fullPath), $baseDir) !== 0) {
    echo json_encode(['success' => true, 'files' => [], 'folder' => $folder]);
    exit;
}

$files = [];
foreach (scandir($fullPath) as $file) {
    if ($file === '.' || $file === '..' || $file === '_thumbs') continue;
    if (preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $file)) {
        $files[] = $file;
    }
}

natsort($files);
$files = array_values($files);

echo json_encode([
    'success' => true,
    'folder' => $folder,
    'count' => count($files),
    'files' => $files
]);

