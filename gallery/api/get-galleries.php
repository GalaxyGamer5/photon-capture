<?php
// gallery/api/get-galleries.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true || !isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

require_once __DIR__ . '/../../includes/db.php';

$user = $_SESSION['user'];
$userId = (int)$user['id'];

try {
    $db = getDB();

    $stmt = $db->prepare("SELECT id, title, folder, image_count, is_protected, created_at FROM galleries WHERE user_id = ? ORDER BY id ASC");
    $stmt->execute([$userId]);
    $galleries = $stmt->fetchAll();

    // Fallback if user has no entries in galleries yet
    if (empty($galleries) && !empty($user['folder'])) {
        $insert = $db->prepare("INSERT OR IGNORE INTO galleries (user_id, title, folder, image_count, is_protected) VALUES (?, ?, ?, ?, ?)");
        $insert->execute([$userId, 'Hauptgalerie', $user['folder'], (int)($user['imageCount'] ?? 0), !empty($user['isProtected']) ? 1 : 0]);

        $stmt->execute([$userId]);
        $galleries = $stmt->fetchAll();
    }

    $baseAssetsDir = realpath(__DIR__ . '/../../') . '/gallery/assets/';
    $result = [];

    foreach ($galleries as $g) {
        $folder = $g['folder'];
        $folderPath = $baseAssetsDir . $folder;

        // Count real images on disk if needed
        $actualCount = 0;
        $cover = null;
        if (is_dir($folderPath)) {
            $files = scandir($folderPath);
            $imgFiles = [];
            foreach ($files as $f) {
                if ($f === '.' || $f === '..' || $f === '_thumbs') continue;
                if (preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $f)) {
                    $imgFiles[] = $f;
                }
            }
            natsort($imgFiles);
            $actualCount = count($imgFiles);
            if (!empty($imgFiles)) {
                $cover = 'api/get-image.php?f=' . urlencode($folder) . '&i=' . urlencode(reset($imgFiles)) . '&thumb=1';
            }
        }

        $result[] = [
            'id' => (int)$g['id'],
            'title' => $g['title'] ?: 'Galerie',
            'folder' => $folder,
            'imageCount' => $actualCount ?: (int)$g['image_count'],
            'isProtected' => (bool)$g['is_protected'],
            'coverImage' => $cover,
            'createdAt' => $g['created_at']
        ];
    }

    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $userId,
            'username' => $user['username'],
            'name' => $user['name']
        ],
        'galleries' => $result
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

