<?php
// admin/api/delete-testimonial.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';

$data = json_decode(file_get_contents('php://input'), true);
if (empty($data['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Keine ID angegeben.']);
    exit;
}

$id = $data['id'];

try {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM testimonials WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

