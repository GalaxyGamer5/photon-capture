<?php
// Start session
session_start();

// Set JSON header
header('Content-Type: application/json');

// Check if user is authenticated
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true && isset($_SESSION['user'])) {
    echo json_encode([
        'success' => true,
        'authenticated' => true,
        'user' => $_SESSION['user']
    ]);
} else {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'authenticated' => false,
        'error' => 'Not authenticated'
    ]);
}
