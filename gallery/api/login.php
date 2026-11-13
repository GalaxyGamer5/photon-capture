<?php
// gallery/api/login.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['username']) || !isset($input['password'])) {
    echo json_encode(['success' => false, 'error' => 'Username and password required']);
    exit;
}

$username = trim($input['username']);
$password = trim($input['password']);
$passwordHash = sha1($password);

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, username, password_hash, name, folder, image_count, is_protected FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && $user['password_hash'] === $passwordHash) {
        $_SESSION['authenticated'] = true;
        $_SESSION['user'] = [
            'id' => (string)$user['id'],
            'username' => $user['username'],
            'name' => $user['name'],
            'folder' => $user['folder'],
            'imageCount' => (int)$user['image_count'],
            'isProtected' => (bool)$user['is_protected']
        ];

        echo json_encode([
            'success' => true,
            'user' => $_SESSION['user']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Ungültiger Benutzername oder Passwort'
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
