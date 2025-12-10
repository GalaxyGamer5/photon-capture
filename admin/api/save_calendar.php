<?php
// admin/api/save_calendar.php
header('Content-Type: application/json');

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

// Path to data file
$file = '../../data/calendar.json';

// Check if file exists and is writable
if (file_exists($file) && !is_writable($file)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Permission denied: calendar.json is not writable']);
    exit;
}

// Save to file
if (file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to write to file']);
}
