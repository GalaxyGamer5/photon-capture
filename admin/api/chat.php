<?php
// admin/api/chat.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = getDB();

    if ($method === 'GET') {
        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
        $username = isset($_GET['username']) ? trim($_GET['username']) : '';

        // If specific user requested, return conversation
        if ($userId > 0 || $username !== '') {
            if ($userId === 0 && $username !== '') {
                $uStmt = $db->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
                $uStmt->execute([$username]);
                $userId = (int)$uStmt->fetchColumn();
            }

            if ($userId === 0) {
                echo json_encode(['success' => false, 'error' => 'User not found']);
                exit;
            }

            $stmt = $db->prepare("
                SELECT id, user_id, sender, sender_name, message, is_read, created_at 
                FROM chat_messages 
                WHERE user_id = ? 
                ORDER BY id ASC
            ");
            $stmt->execute([$userId]);
            $messages = $stmt->fetchAll();

            // Mark customer messages as read by admin
            $upd = $db->prepare("
                UPDATE chat_messages 
                SET is_read = 1 
                WHERE user_id = ? AND sender = 'customer' AND is_read = 0
            ");
            $upd->execute([$userId]);

            echo json_encode([
                'success' => true,
                'user_id' => $userId,
                'messages' => $messages ?: []
            ]);
            exit;
        }

        // Otherwise return summary list of conversations across all users
        $convStmt = $db->query("
            SELECT 
                u.id AS user_id, 
                u.username, 
                u.name,
                (SELECT message FROM chat_messages WHERE user_id = u.id ORDER BY id DESC LIMIT 1) AS last_message,
                (SELECT created_at FROM chat_messages WHERE user_id = u.id ORDER BY id DESC LIMIT 1) AS last_activity,
                (SELECT COUNT(*) FROM chat_messages WHERE user_id = u.id AND sender = 'customer' AND is_read = 0) AS unread_count,
                (SELECT COUNT(*) FROM chat_messages WHERE user_id = u.id) AS total_messages
            FROM users u
            WHERE (SELECT COUNT(*) FROM chat_messages WHERE user_id = u.id) > 0
            ORDER BY last_activity DESC
        ");
        $conversations = $convStmt->fetchAll();

        // Total unread for badge
        $totalUnread = $db->query("SELECT COUNT(*) FROM chat_messages WHERE sender = 'customer' AND is_read = 0")->fetchColumn();

        echo json_encode([
            'success' => true,
            'total_unread' => (int)$totalUnread,
            'conversations' => $conversations ?: []
        ]);
        exit;
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $userId = isset($input['user_id']) ? (int)$input['user_id'] : 0;
        $username = isset($input['username']) ? trim($input['username']) : '';
        $text = trim($input['message'] ?? '');

        if ($userId === 0 && $username !== '') {
            $uStmt = $db->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
            $uStmt->execute([$username]);
            $userId = (int)$uStmt->fetchColumn();
        }

        if ($userId === 0 || $text === '') {
            echo json_encode(['success' => false, 'error' => 'user_id and message text required']);
            exit;
        }

        $ins = $db->prepare("
            INSERT INTO chat_messages (user_id, sender, sender_name, message, is_read, created_at)
            VALUES (?, 'admin', 'Jonathan (Fotograf)', ?, 0, datetime('now'))
        ");
        $ins->execute([$userId, $text]);
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
