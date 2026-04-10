<?php
/**
 * admin/api/list-gallery-files.php
 *
 * Returns the actual image filenames present in a gallery folder on disk.
 * This is the source-of-truth for the admin gallery UI so it always knows
 * which files really exist (even after renaming that happens after deletions).
 *
 * Query params:
 *   folder  – the gallery folder name (e.g. "smithfamily")
 *
 * Response:
 *   { "success": true, "files": ["1.jpg", "2.jpg", ...] }
 */

header('Content-Type: application/json');

$folder = isset($_GET['folder']) ? trim($_GET['folder']) : '';

// Basic security: forbid directory traversal
if ($folder === '' || strpos($folder, '..') !== false || preg_match('/[\/\\\\]/', $folder)) {
    echo json_encode(['success' => false, 'error' => 'Invalid folder name']);
    exit;
}

$baseDir  = realpath(__DIR__ . '/../../gallery/assets');
$fullPath = $baseDir . DIRECTORY_SEPARATOR . $folder;

if (!$baseDir || !is_dir($fullPath) || strpos(realpath($fullPath), $baseDir) !== 0) {
    // Return empty list rather than an error so the UI degrades gracefully
    echo json_encode(['success' => true, 'files' => []]);
    exit;
}

$files = [];
foreach (scandir($fullPath) as $file) {
    if (preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $file)) {
        $files[] = $file;
    }
}

// Natural sort so 2.jpg comes before 10.jpg
natsort($files);
$files = array_values($files);

echo json_encode(['success' => true, 'files' => $files]);
