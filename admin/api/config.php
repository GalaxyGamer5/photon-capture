<?php
// admin/api/config.php

// Constants
ini_set('display_errors', 0); // Disable error display to prevent JSON breakage
ini_set('log_errors', 1);     // Enable error logging
error_reporting(E_ALL);

define('DATA_FILE', __DIR__ . '/../data/availability.json');
define('ADMIN_PASSWORD_HASH', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'); 

// Helper function for JSON response
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Helper to ensure data directory exists
function ensureDataDirectory() {
    if (!file_exists(dirname(DATA_FILE))) {
        @mkdir(dirname(DATA_FILE), 0755, true);
    }
    if (!file_exists(DATA_FILE)) {
        @file_put_contents(DATA_FILE, '{}');
    }
}

// CORS Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle Preflight Request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
?>
