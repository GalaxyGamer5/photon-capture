<?php
// admin/api/get-dashboard-stats.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';

$stats = [
    'inquiries' => 0,
    'galleries' => 0,
    'portfolio' => 0,
    'testimonials' => 0,
    'favorites' => 0
];

try {
    $db = getDB();
    $stats['inquiries'] = (int)$db->query("SELECT COUNT(*) FROM inquiries")->fetchColumn();
    $stats['galleries'] = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['portfolio'] = (int)$db->query("SELECT COUNT(*) FROM portfolio")->fetchColumn();
    $stats['testimonials'] = (int)$db->query("SELECT COUNT(*) FROM testimonials")->fetchColumn();
    $stats['favorites'] = (int)$db->query("SELECT COUNT(*) FROM favorites")->fetchColumn();

    echo json_encode($stats);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
