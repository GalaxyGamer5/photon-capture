<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0); // Hide from HTML output

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid or empty JSON received: ' . $input]);
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
