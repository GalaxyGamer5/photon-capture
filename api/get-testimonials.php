<?php
// api/get-testimonials.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../includes/db.php';

try {
    $db = getDB();
    $stmt = $db->query("SELECT id, name, rating, text, date, approved FROM testimonials WHERE approved = 1 ORDER BY date DESC");
    $testimonials = $stmt->fetchAll();

    foreach ($testimonials as &$t) {
        $t['rating'] = (int) $t['rating'];
        $t['approved'] = (bool) $t['approved'];
    }

    echo json_encode([
        'success' => true,
        'testimonials' => $testimonials ?: []
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
