<?php
header('Content-Type: application/json');

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON data received: ' . json_last_error_msg()]);
    exit;
}

if (empty($data)) {
    echo json_encode(['success' => false, 'error' => 'Received empty data']);
    exit;
}

$file = __DIR__ . '/../../data/pricing.json';

// Ensure the directory exists
$dir = dirname($file);
if (!is_dir($dir)) {
    echo json_encode(['success' => false, 'error' => 'Data directory does not exist: ' . $dir]);
    exit;
}

// Check for write permission
if (file_exists($file) && !is_writable($file)) {
    echo json_encode(['success' => false, 'error' => 'Pricing file is not writable. Check permissions.']);
    exit;
}

$jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
if ($jsonData === false) {
    echo json_encode(['success' => false, 'error' => 'Failed to encode JSON.']);
    exit;
}

$result = file_put_contents($file, $jsonData);

if ($result !== false) {
    $firstPkg = isset($data['packages'][0]['title']['de']) ? $data['packages'][0]['title']['de'] : 'N/A';
    echo json_encode(['success' => true, 'bytes' => $result, 'received_first' => $firstPkg]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to write pricing data.']);
}
