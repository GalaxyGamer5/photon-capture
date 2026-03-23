<?php
header('Content-Type: application/json');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid or empty data received']);
    exit;
}

$file = __DIR__ . '/../../data/pricing.json';

// Try to fix permissions if file exists
if (file_exists($file)) {
    @chmod($file, 0777);
}

$jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

if (file_put_contents($file, $jsonData)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to write pricing data. Check file permissions.']);
}
