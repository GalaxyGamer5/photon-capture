<?php
// admin/api/get_users.php
header('Content-Type: application/javascript');

require_once __DIR__ . '/../../includes/db.php';

try {
    $db = getDB();
    $stmt = $db->query("SELECT id, username, password_hash, name, folder, image_count, is_protected FROM users ORDER BY id ASC");
    $rows = $stmt->fetchAll();

    $galStmt = $db->prepare("SELECT id, user_id, title, folder, image_count, is_protected FROM galleries WHERE user_id = ? ORDER BY id ASC");
    $chatStmt = $db->prepare("SELECT COUNT(*) FROM chat_messages WHERE user_id = ? AND sender = 'customer' AND is_read = 0");

    $users = [];
    foreach ($rows as $r) {
        $galStmt->execute([$r['id']]);
        $userGalleries = $galStmt->fetchAll();

        if (empty($userGalleries) && !empty($r['folder'])) {
            $userGalleries = [[
                'id' => 0,
                'user_id' => $r['id'],
                'title' => 'Hauptgalerie',
                'folder' => $r['folder'],
                'image_count' => (int)$r['image_count'],
                'is_protected' => (bool)$r['is_protected']
            ]];
        }

        $chatStmt->execute([$r['id']]);
        $unreadChat = (int)$chatStmt->fetchColumn();

        $users[] = [
            'id' => (string)$r['id'],
            'username' => $r['username'],
            'passwordHash' => $r['password_hash'],
            'name' => $r['name'],
            'folder' => $r['folder'],
            'imageCount' => (int)$r['image_count'],
            'isProtected' => (bool)$r['is_protected'],
            'unreadChatCount' => $unreadChat,
            'galleries' => array_map(function($g) {
                return [
                    'id' => (int)$g['id'],
                    'title' => $g['title'],
                    'folder' => $g['folder'],
                    'imageCount' => (int)$g['image_count'],
                    'isProtected' => (bool)$g['is_protected']
                ];
            }, $userGalleries)
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
