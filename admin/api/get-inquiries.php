<?php
// admin/api/get-inquiries.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';

try {
    $db = getDB();
    $stmt = $db->query("SELECT id, name, email, service, message, unread, created_at AS date FROM inquiries ORDER BY created_at DESC");
    $rows = $stmt->fetchAll();

    $inquiries = [];
    foreach ($rows as $r) {
        $inquiries[] = [
            'id' => $r['id'],
            'date' => $r['date'],
            'name' => $r['name'],
            'email' => $r['email'],
            'service' => $r['service'],
            'message' => $r['message'],
            'unread' => (bool)$r['unread']
        ];
    }

    echo json_encode([
        'success' => true,
        'inquiries' => $inquiries
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
