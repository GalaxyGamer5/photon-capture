<?php
// admin/api/add-to-portfolio-from-gallery.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['photos']) || !is_array($input['photos']) || empty($input['photos'])) {
    echo json_encode(['success' => false, 'error' => 'No photos selected']);
    exit;
}

$category = trim($input['category'] ?? 'portrait');
$allowedCategories = ['portrait', 'event', 'pet'];
if (!in_array($category, $allowedCategories)) {
    $category = 'portrait';
}

$baseDir = realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR;
$portDir = $baseDir . 'assets' . DIRECTORY_SEPARATOR . 'portfolio' . DIRECTORY_SEPARATOR;

if (!file_exists($portDir)) {
    @mkdir($portDir, 0775, true);
}

$db = null;
try {
    $db = getDB();
} catch (Exception $e) {}

$added = 0;
$errors = [];
$now = date('Y-m-d H:i:s');

foreach ($input['photos'] as $item) {
    // $item can be a relative path e.g. "gallery/assets/folder/1.jpg"
    $relPath = is_array($item) ? ($item['path'] ?? '') : (string)$item;
    $relPath = ltrim(str_replace(['../', '..\\', '..'], '', $relPath), '/\\');

    $fullSourcePath = realpath($baseDir . $relPath);

    // Verify source is inside gallery/assets/
    $allowedBase = realpath($baseDir . 'gallery' . DIRECTORY_SEPARATOR . 'assets');
    if (!$fullSourcePath || !$allowedBase || strpos($fullSourcePath, $allowedBase) !== 0 || !is_file($fullSourcePath)) {
        $errors[] = "File not found or not in gallery assets: " . basename($relPath);
        continue;
    }

    $ext = strtolower(pathinfo($fullSourcePath, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
        $errors[] = "Unsupported format: " . basename($relPath);
        continue;
    }

    $id = uniqid('port_');
    $newFilename = $id . '_' . time() . '.' . $ext;
    $targetPath = $portDir . $newFilename;

    if (@copy($fullSourcePath, $targetPath)) {
        @chmod($targetPath, 0666);
        $added++;

        if ($db) {
            try {
                $ins = $db->prepare("INSERT INTO portfolio (id, filename, category, created_at) VALUES (?, ?, ?, ?)");
                $ins->execute([$id, $newFilename, $category, $now]);
            } catch (Exception $e) {
                $errors[] = "DB error for " . basename($relPath) . ": " . $e->getMessage();
            }
        }
    } else {
        $errors[] = "Failed to copy: " . basename($relPath);
    }
}

// Keep data/portfolio.json in sync
if ($added > 0 && $db) {
    try {
        $stmt = $db->query("SELECT id, filename, category, created_at AS date FROM portfolio ORDER BY created_at DESC");
        $all = $stmt->fetchAll();
        $jsonFile = $baseDir . 'data' . DIRECTORY_SEPARATOR . 'portfolio.json';
        @file_put_contents($jsonFile, json_encode(['images' => $all ?: []], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    } catch (Exception $e) {}
}

echo json_encode([
    'success' => $added > 0,
    'added' => $added,
    'errors' => $errors,
    'message' => "{$added} Foto(s) erfolgreich zum Portfolio hinzugefügt!"
]);

