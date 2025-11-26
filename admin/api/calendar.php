<?php
// admin/api/calendar.php
require_once 'config.php';

session_start();

// Helper to check auth
function requireAuth() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }
}

// Handle GET request (Read data - Public)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (file_exists(DATA_FILE)) {
        $data = file_get_contents(DATA_FILE);
        header('Content-Type: application/json');
        echo $data;
    } else {
        echo '{}';
    }
    exit;
}

// Handle POST request (Write data - Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAuth();

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if ($data === null) {
        jsonResponse(['error' => 'Invalid JSON'], 400);
    }

    // Save to file
    if (file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT))) {
        jsonResponse(['success' => true]);
    } else {
        jsonResponse(['error' => 'Failed to save data'], 500);
    }
}
?>
