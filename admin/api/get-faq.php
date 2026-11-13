<?php
// admin/api/get-faq.php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../includes/db.php';

try {
    $db = getDB();
    $stmt = $db->query("SELECT id, question_de, question_en, answer_de, answer_en, sort_order FROM faq ORDER BY sort_order ASC, id ASC");
    $rows = $stmt->fetchAll();

    $items = [];
    foreach ($rows as $r) {
        $items[] = [
            'id' => $r['id'],
            'q' => [
                'de' => $r['question_de'],
                'en' => $r['question_en'] ?: $r['question_de']
            ],
            'a' => [
                'de' => $r['answer_de'],
                'en' => $r['answer_en'] ?: $r['answer_de']
            ]
        ];
    }

    echo json_encode([
        'title' => [
            'de' => 'Häufig gestellte Fragen',
            'en' => 'Frequently Asked Questions'
        ],
        'items' => $items
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
