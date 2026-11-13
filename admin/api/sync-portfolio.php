<?php
// admin/api/sync-portfolio.php
// Re-scans assets/portfolio/ and inserts any missing files into the DB.
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../includes/db.php';

$db = getDB();
$baseDir = realpath(__DIR__ . '/../../');
$portDir = $baseDir . '/assets/portfolio/';

$added   = 0;
$skipped = 0;
$errors  = [];

if (!is_dir($portDir)) {
    echo json_encode(['success' => false, 'error' => 'Portfolio directory not found: ' . $portDir]);
    exit;
}

$allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
$files   = scandir($portDir);

$checkStmt  = $db->prepare("SELECT COUNT(*) FROM portfolio WHERE filename = ?");
$insertStmt = $db->prepare(
    "INSERT OR IGNORE INTO portfolio (id, filename, category, created_at) VALUES (?, ?, ?, ?)"
);

foreach ($files as $file) {
    if ($file === '.' || $file === '..') continue;
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) continue;

    $checkStmt->execute([$file]);
    if ($checkStmt->fetchColumn() > 0) {
        $skipped++;
        continue;
    }

    // Guess category from filename
    $cat = 'portrait';
    $lc  = strtolower($file);
    if (strpos($lc, 'event') !== false) $cat = 'event';
    elseif (strpos($lc, 'pet') !== false || strpos($lc, 'tier') !== false) $cat = 'pet';

    $mtime = @filemtime($portDir . $file);
    try {
        $insertStmt->execute([
            uniqid('img_'),
            $file,
            $cat,
            date('Y-m-d H:i:s', $mtime ?: time())
        ]);
        $added++;
    } catch (Exception $e) {
        $errors[] = $file . ': ' . $e->getMessage();
    }
}

echo json_encode([
    'success' => true,
    'added'   => $added,
    'skipped' => $skipped,
    'errors'  => $errors,
    'message' => "{$added} Bilder hinzugefügt, {$skipped} bereits vorhanden."
]);
