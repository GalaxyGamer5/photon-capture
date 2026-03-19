<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['photon_admin_auth']) || $_SESSION['photon_admin_auth'] !== 'true') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing inquiry ID']);
    exit;
}

$id = $data['id'];
$file = '../../data/inquiries.json';

if (!file_exists($file)) {
    echo json_encode(['success' => false, 'error' => 'Database not found']);
    exit;
}

$db = json_decode(file_get_contents($file), true);
$inquiries = $db['inquiries'] ?? [];
$found = false;

foreach ($inquiries as $index => $item) {
    if ($item['id'] === $id) {
        array_splice($db['inquiries'], $index, 1);
        $found = true;
        break;
    }
}

if (!$found) {
    echo json_encode(['success' => false, 'error' => 'Inquiry not found']);
    exit;
}

file_put_contents($file, json_encode($db, JSON_PRETTY_PRINT));
echo json_encode(['success' => true]);
