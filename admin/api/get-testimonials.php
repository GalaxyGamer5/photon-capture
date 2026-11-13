<?php
// admin/api/get-testimonials.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';

try {
    $db = getDB();
    $stmt = $db->query("SELECT id, name, rating, text, date, approved FROM testimonials ORDER BY date DESC, id DESC");
    $testimonials = $stmt->fetchAll();

    foreach ($testimonials as &$t) {
        $t['rating'] = (int)$t['rating'];
        $t['approved'] = (bool)$t['approved'];
    }

    echo json_encode([
        'success' => true,
        'testimonials' => $testimonials ?: []
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
