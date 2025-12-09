<?php
// admin/api/config.php

// Constants
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('DATA_FILE', __DIR__ . '/../data/availability.json');
define('ADMIN_PASSWORD_HASH', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'); // Hash for 'password' (change this in production!)
// Note: For this demo, I'm using a simple hash. In production, use password_hash('your_password', PASSWORD_DEFAULT) to generate.
// The hash above is for 'password'. 
// Let's use the one for 'photon2024' as requested in the previous code:
// password_hash('photon2024', PASSWORD_DEFAULT) -> $2y$10$s.w/.. (I will generate a real one or use a simple check for now if I can't run PHP to generate)

// Actually, let's use a simple hardcoded hash for 'photon2024' for now to ensure it works without PHP CLI access
// Hash for 'photon2024': $2y$10$X7.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1 (placeholder)
// I'll use a simple comparison for this environment since I can't easily generate a bcrypt hash without running PHP.
// WAIT, I can run PHP!
// I'll create a setup script to generate the hash first.

// Helper function for JSON response
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Ensure data directory exists
if (!file_exists(dirname(DATA_FILE))) {
    mkdir(dirname(DATA_FILE), 0755, true);
}

// Initialize data file if not exists
if (!file_exists(DATA_FILE)) {
    file_put_contents(DATA_FILE, '{}');
}

// CORS Headers
header("Access-Control-Allow-Origin: *"); // Allow all origins (or specify your main domain)
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle Preflight Request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
?>
