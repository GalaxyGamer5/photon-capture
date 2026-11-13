<?php
// admin/api/list-galleries.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';

try {
    $db = getDB();
    $stmt = $db->query("SELECT id, name, folder, image_count FROM users ORDER BY name ASC");
    $rows = $stmt->fetchAll();

    $galleries = [];
    foreach ($rows as $user) {
        $galleries[] = [
            'id' => (string)$user['id'],
            'name' => $user['name'],
            'folder' => $user['folder'],
            'imageCount' => (int)$user['image_count']
        ];
    }

    echo json_encode([
        'success' => true,
        'galleries' => $galleries
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
