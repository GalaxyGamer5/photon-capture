<?php
// admin/api/list-galleries.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';

try {
    $db = getDB();

    $stmt = $db->query("
        SELECT g.id, g.folder, g.title, u.name as user_name, g.image_count 
        FROM galleries g 
        JOIN users u ON g.user_id = u.id 
        ORDER BY u.name ASC, g.title ASC
    ");
    $gRows = $stmt->fetchAll();

    $galleries = [];
    if (!empty($gRows)) {
        foreach ($gRows as $r) {
            $galleries[] = [
                'id' => (string)$r['id'],
                'name' => $r['user_name'] . ' — ' . $r['title'],
                'folder' => $r['folder'],
                'imageCount' => (int)$r['image_count']
            ];
        }
    } else {
        $stmt2 = $db->query("SELECT id, name, folder, image_count FROM users ORDER BY name ASC");
        $rows = $stmt2->fetchAll();
        foreach ($rows as $user) {
            $galleries[] = [
                'id' => (string)$user['id'],
                'name' => $user['name'],
                'folder' => $user['folder'],
                'imageCount' => (int)$user['image_count']
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'galleries' => $galleries
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
