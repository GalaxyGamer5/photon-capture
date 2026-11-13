<?php
// gallery/api/chat.php
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
$userName = $user['name'] ?? $user['username'];

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = getDB();

    if ($method === 'GET') {
        // Fetch all messages for this user
        $stmt = $db->prepare("
            SELECT id, user_id, sender, sender_name, message, is_read, created_at 
            FROM chat_messages 
            WHERE user_id = ? 
            ORDER BY id ASC
        ");
        $stmt->execute([$userId]);
        $messages = $stmt->fetchAll();

        // Mark admin messages as read
        $upd = $db->prepare("
            UPDATE chat_messages 
            SET is_read = 1 
            WHERE user_id = ? AND sender = 'admin' AND is_read = 0
        ");
        $upd->execute([$userId]);

        echo json_encode([
            'success' => true,
            'messages' => $messages ?: []
        ]);
        exit;
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $text = trim($input['message'] ?? '');

        if ($text === '') {
            echo json_encode(['success' => false, 'error' => 'Message text required']);
            exit;
        }

        $ins = $db->prepare("
            INSERT INTO chat_messages (user_id, sender, sender_name, message, is_read, created_at)
            VALUES (?, 'customer', ?, ?, 0, datetime('now'))
        ");
        $ins->execute([$userId, $userName, $text]);
        $newId = $db->lastInsertId();

        $getStmt = $db->prepare("SELECT id, user_id, sender, sender_name, message, is_read, created_at FROM chat_messages WHERE id = ?");
        $getStmt->execute([$newId]);
        $newMsg = $getStmt->fetch();

        echo json_encode([
            'success' => true,
            'message' => $newMsg
        ]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

