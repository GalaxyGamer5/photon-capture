<?php
// gallery/api/submit-review.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true || !isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ungültige Daten empfangen.']);
    exit;
}

$text = trim($input['text'] ?? '');
if (empty($text)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Bitte gib einen kurzen Bewertungstext ein.']);
    exit;
}

$rating = isset($input['rating']) ? (int)$input['rating'] : 5;
if ($rating < 1) $rating = 1;
if ($rating > 5) $rating = 5;

// Use custom display name if provided, otherwise customer's account name
$name = trim($input['name'] ?? '');
if (empty($name)) {
    $name = $_SESSION['user']['name'] ?? $_SESSION['user']['username'] ?? 'Kunde';
}

require_once __DIR__ . '/../../includes/db.php';

try {
    $db = getDB();
    $id = uniqid('test_');
    $date = date('Y-m-d');

    $stmt = $db->prepare("INSERT INTO testimonials (id, name, rating, text, date, approved) VALUES (?, ?, ?, ?, ?, 1)");
    $stmt->execute([$id, $name, $rating, $text, $date]);

    echo json_encode([
        'success' => true,
        'message' => 'Vielen Dank für deine Bewertung!'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Fehler beim Speichern: ' . $e->getMessage()]);
}
