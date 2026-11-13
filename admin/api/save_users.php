<?php
// admin/api/save_users.php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../includes/db.php';

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE || !isset($data['users']) || !is_array($data['users'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

try {
    $db = getDB();
    $db->beginTransaction();

    // Collect submitted usernames
    $submittedUsernames = [];
    $upsertStmt = $db->prepare("
        INSERT INTO users (username, password_hash, name, folder, image_count, is_protected)
        VALUES (?, ?, ?, ?, ?, ?)
        ON CONFLICT(username) DO UPDATE SET
            password_hash = excluded.password_hash,
            name = excluded.name,
            folder = excluded.folder,
            image_count = excluded.image_count,
            is_protected = excluded.is_protected
    ");

    foreach ($data['users'] as $u) {
        if (empty($u['username']))
            continue;
        $uname = trim($u['username']);
        $submittedUsernames[] = $uname;

        $upsertStmt->execute([
            $uname,
            $u['passwordHash'] ?? '',
            $u['name'] ?? $uname,
            $u['folder'] ?? ($uname . '-gallery'),
            (int) ($u['imageCount'] ?? 0),
            isset($u['isProtected']) ? ($u['isProtected'] ? 1 : 0) : 1
        ]);
    }

    // Delete users that were removed from the array
    if (empty($submittedUsernames)) {
        $db->exec("DELETE FROM users");
    } else {
        $placeholders = implode(',', array_fill(0, count($submittedUsernames), '?'));
        $delStmt = $db->prepare("DELETE FROM users WHERE username NOT IN ($placeholders)");
        $delStmt->execute($submittedUsernames);
    }

    $db->commit();

    // Keep users.js and backups in sync
    syncUsersJs($db);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
