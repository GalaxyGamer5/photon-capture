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
    $updGal = $db->prepare("UPDATE galleries SET image_count = ? WHERE id = ?");
    $updUsr = $db->prepare("UPDATE users SET image_count = ? WHERE id = ?");

    $baseAssetsDir = realpath(__DIR__ . '/../../') . '/gallery/assets/';

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

        $processedGalleries = [];
        foreach ($userGalleries as $g) {
            $diskCount = 0;
            $folderPath = $baseAssetsDir . $g['folder'];
            if (is_dir($folderPath)) {
                foreach (scandir($folderPath) as $f) {
                    if ($f !== '.' && $f !== '..' && $f !== '_thumbs' && preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $f)) {
                        $diskCount++;
                    }
                }
            }
            $finalCount = ($diskCount > 0) ? $diskCount : (int)$g['image_count'];
            if ($diskCount > 0 && $diskCount !== (int)$g['image_count'] && !empty($g['id'])) {
                try { $updGal->execute([$diskCount, $g['id']]); } catch(Exception $e){}
            }

            $processedGalleries[] = [
                'id' => (int)$g['id'],
                'title' => $g['title'],
                'folder' => $g['folder'],
                'imageCount' => $finalCount,
                'isProtected' => (bool)$g['is_protected']
            ];
        }

        $totalUserCount = array_sum(array_column($processedGalleries, 'imageCount'));
        if ($totalUserCount > 0 && $totalUserCount !== (int)$r['image_count']) {
            try { $updUsr->execute([$totalUserCount, $r['id']]); } catch(Exception $e){}
        }

        $chatStmt->execute([$r['id']]);
        $unreadChat = (int)$chatStmt->fetchColumn();

        $users[] = [
            'id' => (string)$r['id'],
            'username' => $r['username'],
            'passwordHash' => $r['password_hash'],
            'name' => $r['name'],
            'folder' => $r['folder'],
            'imageCount' => $totalUserCount ?: (int)$r['image_count'],
            'isProtected' => (bool)$r['is_protected'],
            'unreadChatCount' => $unreadChat,
            'galleries' => $processedGalleries
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
