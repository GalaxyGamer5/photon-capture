<?php
// admin/api/users.php
require_once 'config.php';

// Path to users.json (Relative to admin/api/ -> ../../customers/data/users.json)
$usersFile = __DIR__ . '/../../customers/data/users.json';

// Ensure directory exists
if (!file_exists(dirname($usersFile))) {
    mkdir(dirname($usersFile), 0755, true);
}

// Handle GET (Fetch Users)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (file_exists($usersFile)) {
        header('Content-Type: application/json');
        readfile($usersFile);
    } else {
        echo json_encode(['users' => []]);
    }
    exit;
}

// Handle POST (Save Users)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['users'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid data']);
        exit;
    }

    $jsonStr = json_encode(['users' => $data['users']], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    if (file_put_contents($usersFile, $jsonStr) !== false) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save']);
    }
    exit;
}
?>
