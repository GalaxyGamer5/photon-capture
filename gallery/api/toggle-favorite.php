<?php
// gallery/api/toggle-favorite.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true || !isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['image'])) {
    echo json_encode(['success' => false, 'error' => 'Image filename required']);
    exit;
}

require_once __DIR__ . '/../../includes/db.php';

$image = trim($input['image']);
$username = $_SESSION['user']['username'];

try {
    $db = getDB();

    // Check if already favorite
    $checkStmt = $db->prepare("SELECT id FROM favorites WHERE username = ? AND filename = ?");
    $checkStmt->execute([$username, $image]);
    $existing = $checkStmt->fetch();

    $isFavorite = false;
    if ($existing) {
        // Remove
        $delStmt = $db->prepare("DELETE FROM favorites WHERE username = ? AND filename = ?");
        $delStmt->execute([$username, $image]);
        $isFavorite = false;
    } else {
        // Insert
        $insStmt = $db->prepare("INSERT INTO favorites (username, filename) VALUES (?, ?)");
        $insStmt->execute([$username, $image]);
        $isFavorite = true;
    }

    // Get updated list
    $listStmt = $db->prepare("SELECT filename FROM favorites WHERE username = ? ORDER BY id ASC");
    $listStmt->execute([$username]);
    $favorites = $listStmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode(['success' => true, 'isFavorite' => $isFavorite, 'favorites' => $favorites ?: []]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
