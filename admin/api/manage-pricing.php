<?php
session_start();
header('Content-Type: application/json');



$data = json_decode(file_get_contents('php://input'), true);

if ($data === null) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON data received: ' . json_last_error_msg()]);
    exit;
}

$file = __DIR__ . '/../../data/pricing.json';

$result = file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
if ($result !== false) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to write pricing data']);
}
