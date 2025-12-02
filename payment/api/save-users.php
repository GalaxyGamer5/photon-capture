<?php
// admin/api/save-users.php

// Helper function for JSON response
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// CORS Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Handle Preflight Request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['users']) || !is_array($data['users'])) {
    jsonResponse(['error' => 'Invalid data format'], 400);
}

// Path to users.js (Relative to admin/api/ -> ../../customers/data/users.js)
$usersFile = __DIR__ . '/../../customers/data/users.js';

// Create data directory if it doesn't exist
if (!file_exists(dirname($usersFile))) {
    mkdir(dirname($usersFile), 0755, true);
}

// Format the content as a JS file
$jsonStr = json_encode(['users' => $data['users']], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
$jsContent = "window.usersDatabase = " . $jsonStr . ";";

// Write to file
if (file_put_contents($usersFile, $jsContent) !== false) {
    jsonResponse(['success' => true, 'message' => 'Configuration saved successfully']);
} else {
    jsonResponse(['error' => 'Failed to write configuration file'], 500);
}
?>
