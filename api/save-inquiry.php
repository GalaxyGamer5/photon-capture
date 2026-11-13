<?php
// api/save-inquiry.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once __DIR__ . '/../includes/db.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

try {
    $db = getDB();
    $inquiryId = uniqid('inq_');
    $name = htmlspecialchars($data['name'] ?? 'Unknown');
    $email = htmlspecialchars($data['email'] ?? 'Unknown');
    $service = htmlspecialchars($data['service'] ?? 'Unknown');
    $message = htmlspecialchars($data['message'] ?? '');
    $now = date('Y-m-d H:i:s');

    $stmt = $db->prepare("INSERT INTO inquiries (id, name, email, service, message, unread, created_at) VALUES (?, ?, ?, ?, ?, 1, ?)");
    $stmt->execute([$inquiryId, $name, $email, $service, $message, $now]);

    // Automated Calendar Reservation if requested
    if (!empty($data['bookingDate'])) {
        $bDate = trim($data['bookingDate']);
        $calCheck = $db->prepare("SELECT status FROM calendar_events WHERE event_date = ?");
        $calCheck->execute([$bDate]);
        $curr = $calCheck->fetch();

        if (!$curr || $curr['status'] !== 'booked') {
            $calStmt = $db->prepare("INSERT INTO calendar_events (event_date, status, updated_at) VALUES (?, 'reserved', ?) ON CONFLICT(event_date) DO UPDATE SET status = 'reserved', updated_at = excluded.updated_at");
            $calStmt->execute([$bDate, $now]);
        }
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
