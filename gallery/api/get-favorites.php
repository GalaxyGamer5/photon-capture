<?php
// gallery/api/get-favorites.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true || !isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

require_once __DIR__ . '/../../includes/db.php';

$username = $_SESSION['user']['username'];

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT filename FROM favorites WHERE username = ? ORDER BY id ASC");
    $stmt->execute([$username]);
    $favorites = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode(['success' => true, 'favorites' => $favorites ?: []]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
