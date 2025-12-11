<?php
// admin/api/save_users.php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

// Format as JS file content
$jsContent = "// Client-side user database\n";
$jsContent .= "// In a real application, this would be a server-side database\n";
$jsContent .= "window.usersDatabase = " . json_encode($data, JSON_PRETTY_PRINT) . ";";

// Path to data file
$file = '../../customers/data/users.js';

// Check if file exists and is writable
if (file_exists($file) && !is_writable($file)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Permission denied: users.js is not writable']);
    exit;
}

// Save to file
if (file_put_contents($file, $jsContent)) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to write to file']);
}
