<?php
// Start session for admin auth
session_start();

// Check admin authentication


// Set JSON header
header('Content-Type: application/json');

$file = __DIR__ . '/../../data/testimonials.json';

// Get input
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? null;

if (!file_exists($file)) {
    file_put_contents($file, json_encode(['testimonials' => []], JSON_PRETTY_PRINT));
}

$data = json_decode(file_get_contents($file), true);

switch ($action) {
    case 'list':
        echo json_encode(['success' => true, 'testimonials' => $data['testimonials']]);
        break;
    
    case 'add':
        $testimonial = [
            'id' => uniqid('test-'),
            'name' => $input['name'] ?? '',
            'rating' => intval($input['rating'] ?? 5),
            'text' => $input['text'] ?? '',
            'date' => date('Y-m-d'),
            'approved' => $input['approved'] ?? true
        ];
        $data['testimonials'][] = $testimonial;
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo json_encode(['success' => true, 'testimonial' => $testimonial]);
        break;
    
    case 'delete':
        $id = $input['id'] ?? null;
        $data['testimonials'] = array_filter($data['testimonials'], function($t) use ($id) {
            return $t['id'] !== $id;
        });
        $data['testimonials'] = array_values($data['testimonials']);
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo json_encode(['success' => true]);
        break;
    
    case 'toggle':
        $id = $input['id'] ?? null;
        foreach ($data['testimonials'] as &$t) {
            if ($t['id'] === $id) {
                $t['approved'] = !($t['approved'] ?? false);
                break;
            }
        }
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo json_encode(['success' => true]);
        break;
    
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
