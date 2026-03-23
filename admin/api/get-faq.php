<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$file = __DIR__ . '/../../data/faq.json';

if (file_exists($file)) {
    echo file_get_contents($file);
} else {
    echo json_encode(['error' => 'FAQ file not found']);
}
