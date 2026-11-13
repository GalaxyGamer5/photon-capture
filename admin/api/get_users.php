<?php
// admin/api/get_users.php
header('Content-Type: application/javascript');

require_once __DIR__ . '/../../includes/db.php';

try {
    $db = getDB();
    $stmt = $db->query("SELECT id, username, password_hash, name, folder, image_count, is_protected FROM users ORDER BY id ASC");
    $rows = $stmt->fetchAll();

    $users = [];
    foreach ($rows as $r) {
        $users[] = [
            'id' => (string)$r['id'],
            'username' => $r['username'],
            'passwordHash' => $r['password_hash'],
            'name' => $r['name'],
            'folder' => $r['folder'],
            'imageCount' => (int)$r['image_count'],
            'isProtected' => (bool)$r['is_protected']
        ];
    }

    $data = ['users' => $users];
    $jsContent = "// Client-side user database\n";
    $jsContent .= "// Powered by SQLite/MySQL database\n";
    $jsContent .= "window.usersDatabase = " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . ";\n";

    // Also keep local users.js in sync for client fallback
    syncUsersJs($db);

    echo $jsContent;
} catch (Exception $e) {
    http_response_code(500);
    echo "console.error('Database error: " . addslashes($e->getMessage()) . "');";
}
