<?php
// Public API - no authentication required
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$file = '../data/portfolio.json';

if (!file_exists($file)) {
    echo json_encode(['success' => true, 'images' => []]);
    exit;
}

$data = json_decode(file_get_contents($file), true);

// Sort by date (newest first)
$images = $data['images'] ?? [];
usort($images, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

echo json_encode([
    'success' => true,
    'images' => array_values($images)
]);
