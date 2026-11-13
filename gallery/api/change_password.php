<?php
// gallery/api/change_password.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['userId']) || !isset($input['oldPasswordHash']) || !isset($input['newPasswordHash'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$userId = $input['userId'];
$oldHash = $input['oldPasswordHash'];
$newHash = $input['newPasswordHash'];

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, password_hash FROM users WHERE id = ? OR username = ? LIMIT 1");
    $stmt->execute([$userId, $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    if ($user['password_hash'] !== $oldHash) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Altes Passwort ist nicht korrekt']);
        exit;
    }

    $updateStmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $updateStmt->execute([$newHash, $user['id']]);

    syncUsersJs($db);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
