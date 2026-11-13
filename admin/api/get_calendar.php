<?php
// admin/api/get_calendar.php
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';

try {
    $db = getDB();
    $stmt = $db->query("SELECT event_date, status FROM calendar_events ORDER BY event_date ASC");
    $rows = $stmt->fetchAll();

    $events = [];
    foreach ($rows as $r) {
        $events[$r['event_date']] = ['status' => $r['status']];
    }

    echo json_encode($events ?: (object)[]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
