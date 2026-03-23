<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$file = __DIR__ . '/../../data/pricing.json';

if (file_exists($file)) {
    echo file_get_contents($file);
} else {
    echo json_encode(['error' => 'Pricing file not found']);
}
