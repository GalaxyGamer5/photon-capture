<?php
// admin/api/save_calendar.php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../includes/db.php';

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

try {
    $db = getDB();
    $db->beginTransaction();

    $db->exec("DELETE FROM calendar_events");

    $stmt = $db->prepare("INSERT INTO calendar_events (event_date, status, updated_at) VALUES (?, ?, ?)");
    $now = date('Y-m-d H:i:s');

    foreach ($data as $date => $val) {
        $status = is_array($val) ? ($val['status'] ?? 'available') : (string) $val;
        $stmt->execute([$date, $status, $now]);
    }

    $db->commit();

    // Sync to data/calendar.json
    $file = __DIR__ . '/../../data/calendar.json';
    @file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    @chmod($file, 0777);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
