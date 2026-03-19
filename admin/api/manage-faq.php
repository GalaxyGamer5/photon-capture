<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['photon_admin_auth']) || $_SESSION['photon_admin_auth'] !== 'true') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

$file = '../../data/faq.json';

if (file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to write FAQ data']);
}
