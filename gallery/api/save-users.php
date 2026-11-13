<?php
// gallery/api/save-users.php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header('Content-Type: application/json');

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        jsonResponse(['status' => 'ok', 'message' => 'PHP script is running']);
    }
    jsonResponse(['error' => 'Method not allowed'], 405);
}

require_once __DIR__ . '/../../includes/db.php';

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data && !empty($_POST['users'])) {
    $data = ['users' => $_POST['users']];
}

if (!isset($data['users']) || !is_array($data['users'])) {
    jsonResponse(['error' => 'Invalid data format'], 400);
}

try {
    $db = getDB();
    $db->beginTransaction();

    $stmt = $db->prepare("
        INSERT INTO users (username, password_hash, name, folder, image_count, is_protected, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, datetime('now'))
        ON CONFLICT(username) DO UPDATE SET
            password_hash = excluded.password_hash,
            name          = excluded.name,
            folder        = excluded.folder,
            image_count   = excluded.image_count,
            is_protected  = excluded.is_protected,
            updated_at    = datetime('now')
    ");

    $keptUsers = [];
    foreach ($data['users'] as $u) {
        $username = trim($u['username'] ?? '');
        if ($username === '') continue;
        $keptUsers[] = $username;

        $stmt->execute([
            $username,
            $u['passwordHash'] ?? '',
            $u['name'] ?? $username,
            $u['folder'] ?? ($username . '-gallery'),
            (int)($u['imageCount'] ?? 0),
            !empty($u['isProtected']) ? 1 : 0
        ]);
    }

    $db->commit();
    syncUsersJs($db);

    jsonResponse(['success' => true, 'message' => 'Configuration saved successfully']);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
}
