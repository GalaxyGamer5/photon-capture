<?php
// admin/api/get_calendar.php
header('Content-Type: application/json');

// Path to data file
$file = '../../data/calendar.json';

if (!file_exists($file)) {
    // Return empty object if file doesn't exist yet, or 404
    // Returning empty object is safer for frontend init
    echo "{}";
    exit;
}

if (!is_readable($file)) {
    http_response_code(500);
    echo json_encode(['error' => 'Permission denied: calendar.json is not readable']);
    exit;
}

readfile($file);
?>
