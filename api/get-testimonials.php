<?php
// Public API - no authentication required
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$file = __DIR__ . '/../data/testimonials.json';

if (!file_exists($file)) {
    echo json_encode(['success' => false, 'error' => 'Testimonials not found']);
    exit;
}

$data = json_decode(file_get_contents($file), true);

// Filter only approved testimonials
$approved = array_filter($data['testimonials'] ?? [], function($t) {
    return $t['approved'] === true;
});

// Sort by date (newest first)
usort($approved, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

echo json_encode([
    'success' => true,
    'testimonials' => array_values($approved)
]);
