<?php
// admin/api/db-import.php
// Accepts a .db file upload and replaces the current photon.db with it.

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

if (empty($_FILES['dbfile']) || $_FILES['dbfile']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['dbfile']['error'] ?? 'no file';
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Keine Datei empfangen (error code: ' . $errCode . ')']);
    exit;
}

$upload = $_FILES['dbfile'];

// Basic validation: SQLite files start with "SQLite format 3\000"
$fh = fopen($upload['tmp_name'], 'rb');
$magic = fread($fh, 16);
fclose($fh);
if (strpos($magic, 'SQLite format 3') !== 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ungültige Datei – keine SQLite-Datenbank.']);
    exit;
}

$dbPath = getDBPath();
if (!$dbPath) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB-Pfad nicht gefunden.']);
    exit;
}

// Make a quick local safety snapshot before overwriting
$backupDir = dirname($dbPath) . DIRECTORY_SEPARATOR . 'backups';
if (!is_dir($backupDir)) @mkdir($backupDir, 0755, true);
$snap = $backupDir . DIRECTORY_SEPARATOR . 'pre_import_' . date('Y-m-d_H-i-s') . '.db';
@copy($dbPath, $snap);

// Overwrite with uploaded file
if (!move_uploaded_file($upload['tmp_name'], $dbPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Konnte Datenbank nicht ersetzen.']);
    exit;
}

@chmod($dbPath, 0664);

// Re-sync users.js from the new DB
try {
    $db = getDB(true); // force fresh connection
    syncUsersJs($db);
} catch (Exception $e) {
    // Non-fatal – DB restored but sync failed
}

echo json_encode([
    'success'  => true,
    'message'  => 'Datenbank erfolgreich importiert.',
    'snapshot' => basename($snap)
]);
