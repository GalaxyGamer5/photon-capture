<?php
// api/get-portfolio.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../includes/db.php';

try {
    $db = getDB();
    $stmt = $db->query("SELECT id, filename, category, created_at AS date, focal_length, aperture, iso, shutter_speed, camera_model FROM portfolio ORDER BY created_at DESC");
    $images = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'images' => $images ?: []
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
