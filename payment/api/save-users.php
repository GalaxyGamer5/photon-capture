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
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed_origins = [
    'https://gallery.photon-capture.de',
    'https://www.gallery.photon-capture.de',
    'http://localhost:3000', // For local testing
    'http://127.0.0.1:3000'
];

if (in_array($origin, $allowed_origins) || empty($origin)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: *"); // Fallback
}

header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle Preflight Request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
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
