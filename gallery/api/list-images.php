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

// Enrich with EXIF metadata from gallery_photos table
$exifMap = [];
try {
    $db = getDB();
    // Resolve gallery_id from folder name
    $gStmt = $db->prepare("SELECT id FROM galleries WHERE folder = ? LIMIT 1");
    $gStmt->execute([$folder]);
    $galleryRow = $gStmt->fetch();
    if ($galleryRow) {
        $pStmt = $db->prepare(
            "SELECT filename, focal_length, aperture, iso, shutter_speed, camera_model, taken_at
             FROM gallery_photos WHERE gallery_id = ?"
        );
        $pStmt->execute([$galleryRow['id']]);
        foreach ($pStmt->fetchAll() as $row) {
            $exifMap[$row['filename']] = [
                'focal_length'  => $row['focal_length'],
                'aperture'      => $row['aperture'],
                'iso'           => $row['iso'],
                'shutter_speed' => $row['shutter_speed'],
                'camera_model'  => $row['camera_model'],
                'taken_at'      => $row['taken_at'],
            ];
        }
    }
} catch (Exception $e) {}

// Build enriched file list
$enriched = array_map(function($f) use ($exifMap) {
    $meta = $exifMap[$f] ?? [];
    return array_merge(['filename' => $f], $meta);
}, $files);

echo json_encode([
    'success' => true,
    'folder'  => $folder,
    'count'   => count($files),
    'files'   => $files,    // keep raw list for backwards compat
    'photos'  => $enriched  // new: enriched list with EXIF
]);

