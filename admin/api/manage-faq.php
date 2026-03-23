<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid or empty JSON received']);
    exit;
}

$file = __DIR__ . '/../../data/faq.json';
$jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

if (!$jsonData) {
    echo json_encode(['success' => false, 'error' => 'JSON Encoding failed']);
    exit;
}

// If file exists and is not writable, TRY deleting it (requires directory write permission)
if (file_exists($file) && !is_writable($file)) {
    @unlink($file);
}

if (file_put_contents($file, $jsonData)) {
    @chmod($file, 0777); // Ensure it stays writable
    echo json_encode(['success' => true]);
} else {
    $error = error_get_last();
    echo json_encode(['success' => false, 'error' => 'Failed to write to file: ' . ($error['message'] ?? 'Unknown error')]);
}
