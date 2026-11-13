<?php
// admin/api/manage-faq.php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../includes/db.php';

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['items']) || !is_array($data['items'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid or empty JSON received']);
    exit;
}

try {
    $db = getDB();
    $db->beginTransaction();

    $db->exec("DELETE FROM faq");

    $stmt = $db->prepare("INSERT INTO faq (id, question_de, question_en, answer_de, answer_en, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
    $order = 0;
    foreach ($data['items'] as $item) {
        $order++;
        $stmt->execute([
            $item['id'] ?? uniqid('faq_'),
            $item['q']['de'] ?? '',
            $item['q']['en'] ?? '',
            $item['a']['de'] ?? '',
            $item['a']['en'] ?? '',
            $order
        ]);
    }

    $db->commit();

    // Also sync to data/faq.json for file fallback
    $file = __DIR__ . '/../../data/faq.json';
    @file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    @chmod($file, 0777);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
