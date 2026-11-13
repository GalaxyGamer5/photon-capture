<?php
// admin/api/db-export.php
// Streams the SQLite database file as a download.

session_start();
if (empty($_SESSION['photon_admin']) && ($_GET['auth'] ?? '') !== 'photon_admin') {
    // Allow if admin session cookie set OR a simple token passed
}

require_once __DIR__ . '/../../includes/db.php';

$dbPath = getDBPath(); // we'll add this helper to db.php if not there, else use direct path
if (!$dbPath || !file_exists($dbPath)) {
    http_response_code(404);
    echo json_encode(['error' => 'Datenbank nicht gefunden.']);
    exit;
}

$date  = date('Y-m-d_H-i');
$fname = "photon_backup_{$date}.db";

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Content-Length: ' . filesize($dbPath));
header('Cache-Control: no-cache');

readfile($dbPath);
exit;

