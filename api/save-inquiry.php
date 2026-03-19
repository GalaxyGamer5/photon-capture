<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

$file = '../data/inquiries.json';

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

if (file_put_contents($file, json_encode($db, JSON_PRETTY_PRINT))) {
    // Optional: send alert email to admin
    // @mail("admin@photoncapture.com", "Neue Nachricht im Dashboard", "Du hast eine neue ungelesene Nachricht.");
    
    echo json_encode(['success' => true, 'id' => $inquiry['id']]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to save to database']);
}
