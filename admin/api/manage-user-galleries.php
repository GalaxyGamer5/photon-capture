<?php
// admin/api/manage-user-galleries.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = getDB();
    $baseAssetsDir = realpath(__DIR__ . '/../../') . '/gallery/assets/';

    if ($method === 'GET') {
        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
        $username = isset($_GET['username']) ? trim($_GET['username']) : '';

        if ($userId === 0 && $username !== '') {
            $uStmt = $db->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
            $uStmt->execute([$username]);
            $userId = (int)$uStmt->fetchColumn();
        }

        if ($userId === 0) {
            echo json_encode(['success' => false, 'error' => 'user_id or username required']);
            exit;
        }

        $stmt = $db->prepare("SELECT id, user_id, title, folder, image_count, is_protected, created_at FROM galleries WHERE user_id = ? ORDER BY id ASC");
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();

        // Count physical images
        foreach ($rows as &$r) {
            $folderPath = $baseAssetsDir . $r['folder'];
            if (is_dir($folderPath)) {
                $files = scandir($folderPath);
                $imgCount = 0;
                foreach ($files as $f) {
                    if (preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $f)) $imgCount++;
                }
                $r['actual_image_count'] = $imgCount;
            } else {
                $r['actual_image_count'] = (int)$r['image_count'];
            }
        }

        echo json_encode([
            'success' => true,
            'galleries' => $rows ?: []
        ]);
        exit;
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';

        if ($action === 'add') {
            $userId = isset($input['user_id']) ? (int)$input['user_id'] : 0;
            $username = isset($input['username']) ? trim($input['username']) : '';
            $title = trim($input['title'] ?? '');
            $folder = trim($input['folder'] ?? '');
            $isProtected = !empty($input['is_protected']) ? 1 : 0;

            if ($userId === 0 && $username !== '') {
                $uStmt = $db->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
                $uStmt->execute([$username]);
                $userId = (int)$uStmt->fetchColumn();
            }

            // Get username for auto-slugging
            $uStmt = $db->prepare("SELECT username, folder FROM users WHERE id = ? LIMIT 1");
            $uStmt->execute([$userId]);
            $userRow = $uStmt->fetch();
            if (!$userRow) {
                echo json_encode(['success' => false, 'error' => 'User not found']);
                exit;
            }

            if ($title === '') {
                $title = 'Neue Galerie';
            }

            if ($folder === '') {
                // Auto slug
                $slug = strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '-', $title));
                $slug = trim($slug, '-');
                $folder = $userRow['username'] . '-' . ($slug ?: 'gallery-' . time());
            } else {
                $folder = preg_replace('/[^a-zA-Z0-9_\-]/', '-', $folder);
            }

            // Create assets directory
            $targetPath = $baseAssetsDir . $folder;
            if (!is_dir($targetPath)) {
                @mkdir($targetPath, 0775, true);
                @mkdir($targetPath . '/_thumbs', 0775, true);
            }

            $ins = $db->prepare("
                INSERT INTO galleries (user_id, title, folder, image_count, is_protected, created_at)
                VALUES (?, ?, ?, 0, ?, datetime('now'))
            ");
            $ins->execute([$userId, $title, $folder, $isProtected]);
            $galleryId = $db->lastInsertId();

            syncUsersJs($db);

            echo json_encode([
                'success' => true,
                'gallery_id' => $galleryId,
                'folder' => $folder,
                'title' => $title
            ]);
            exit;
        }

        if ($action === 'edit') {
            $galleryId = (int)($input['gallery_id'] ?? 0);
            $title = trim($input['title'] ?? '');
            $isProtected = isset($input['is_protected']) ? ((int)$input['is_protected']) : null;

            if ($galleryId === 0) {
                echo json_encode(['success' => false, 'error' => 'gallery_id required']);
                exit;
            }

            $fields = [];
            $vals = [];
            if ($title !== '') {
                $fields[] = "title = ?";
                $vals[] = $title;
            }
            if ($isProtected !== null) {
                $fields[] = "is_protected = ?";
                $vals[] = $isProtected ? 1 : 0;
            }

            if (!empty($fields)) {
                $vals[] = $galleryId;
                $sql = "UPDATE galleries SET " . implode(', ', $fields) . " WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($vals);
                syncUsersJs($db);
            }

            echo json_encode(['success' => true]);
            exit;
        }

        if ($action === 'delete') {
            $galleryId = (int)($input['gallery_id'] ?? 0);
            $deleteFiles = !empty($input['delete_files']);

            if ($galleryId === 0) {
                echo json_encode(['success' => false, 'error' => 'gallery_id required']);
                exit;
            }

            $stmt = $db->prepare("SELECT folder FROM galleries WHERE id = ? LIMIT 1");
            $stmt->execute([$galleryId]);
            $folder = $stmt->fetchColumn();

            $del = $db->prepare("DELETE FROM galleries WHERE id = ?");
            $del->execute([$galleryId]);

            if ($deleteFiles && $folder && is_dir($baseAssetsDir . $folder)) {
                $dir = $baseAssetsDir . $folder;
                // Delete thumbs
                if (is_dir($dir . '/_thumbs')) {
                    foreach (scandir($dir . '/_thumbs') as $f) {
                        if ($f !== '.' && $f !== '..') @unlink($dir . '/_thumbs/' . $f);
                    }
                    @rmdir($dir . '/_thumbs');
                }
                foreach (scandir($dir) as $f) {
                    if ($f !== '.' && $f !== '..') @unlink($dir . '/' . $f);
                }
                @rmdir($dir);
            }

            syncUsersJs($db);

            echo json_encode(['success' => true]);
            exit;
        }

        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
