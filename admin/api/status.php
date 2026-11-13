<?php
// admin/api/status.php
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';

$dbStatus = 'error';
$dbDriver = 'unknown';

try {
    $db = getDB();
    $dbDriver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $dbStatus = 'connected';
} catch (Exception $e) {
    $dbStatus = 'error: ' . $e->getMessage();
}

echo json_encode([
    'status' => 'ok',
    'php_version' => phpversion(),
    'database_status' => $dbStatus,
    'database_driver' => $dbDriver,
    'writable_data' => is_writable(dirname(DB_SQLITE_PATH))
]);
