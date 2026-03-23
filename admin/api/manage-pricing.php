<?php
header('Content-Type: application/json');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid or empty data received']);
    exit;
}

$file = __DIR__ . '/../../data/pricing.json';
$jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

if (file_put_contents($file, $jsonData)) {
    $firstTitle = isset($data['packages'][0]['title']['de']) ? $data['packages'][0]['title']['de'] : 'N/A';
    echo json_encode(['success' => true, 'bytes' => strlen($jsonData), 'received_first' => $firstTitle, 'path' => realpath($file)]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to write pricing data to ' . $file]);
}
