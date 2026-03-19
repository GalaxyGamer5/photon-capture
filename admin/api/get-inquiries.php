<?php
session_start();
header('Content-Type: application/json');



$file = __DIR__ . '/../../data/inquiries.json';

if (!file_exists($file)) {
    echo json_encode(['success' => true, 'inquiries' => []]);
    exit;
}

$db = json_decode(file_get_contents($file), true);

// Sort newest first
$inquiries = $db['inquiries'] ?? [];
usort($inquiries, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

echo json_encode([
    'success' => true,
    'inquiries' => $inquiries
]);
