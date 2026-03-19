<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

$file = __DIR__ . '/../data/inquiries.json';

if (!file_exists($file)) {
    $db = ['inquiries' => []];
} else {
    $db = json_decode(file_get_contents($file), true) ?: ['inquiries' => []];
}

// Create new inquiry object
$inquiry = [
    'id' => uniqid('inq_'),
    'date' => date('c'), // ISO 8601 date
    'name' => htmlspecialchars($data['name'] ?? 'Unknown'),
    'email' => htmlspecialchars($data['email'] ?? 'Unknown'),
    'service' => htmlspecialchars($data['service'] ?? 'Unknown'),
    'message' => htmlspecialchars($data['message'] ?? ''),
    'unread' => true
];

// Prepend to top
array_unshift($db['inquiries'], $inquiry);

if (file_put_contents($file, json_encode($db, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    // Automated Calendar Reservation
    if (!empty($data['bookingDate'])) {
        $calendarFile = __DIR__ . '/../data/calendar.json';
        $calendarData = [];
        
        if (file_exists($calendarFile)) {
            $calendarData = json_decode(file_get_contents($calendarFile), true) ?: [];
        }
        
        // Only mark as reserved if not already booked
        $currentStatus = $calendarData[$data['bookingDate']]['status'] ?? '';
        if ($currentStatus !== 'booked') {
            $calendarData[$data['bookingDate']] = ['status' => 'reserved'];
            file_put_contents($calendarFile, json_encode($calendarData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    // Optional: send alert email to admin
    // @mail("admin@photoncapture.com", "Neue Nachricht im Dashboard", "Du hast eine neue ungelesene Nachricht.");
    
    echo json_encode(['success' => true, 'id' => $inquiry['id']]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to save to database']);
}
