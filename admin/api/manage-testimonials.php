<?php
// admin/api/manage-testimonials.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? null;

try {
    $db = getDB();

    switch ($action) {
        case 'list':
            $stmt = $db->query("SELECT id, name, rating, text, date, approved FROM testimonials ORDER BY date DESC");
            $testimonials = $stmt->fetchAll();
            foreach ($testimonials as &$t) {
                $t['rating'] = (int)$t['rating'];
                $t['approved'] = (bool)$t['approved'];
            }
            echo json_encode(['success' => true, 'testimonials' => $testimonials ?: []]);
            break;

        case 'add':
            $id = uniqid('test-');
            $name = trim($input['name'] ?? '');
            $rating = intval($input['rating'] ?? 5);
            $text = trim($input['text'] ?? '');
            $date = date('Y-m-d');
            $approved = isset($input['approved']) ? ($input['approved'] ? 1 : 0) : 1;

            $stmt = $db->prepare("INSERT INTO testimonials (id, name, rating, text, date, approved) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id, $name, $rating, $text, $date, $approved]);

            $testimonial = [
                'id' => $id,
                'name' => $name,
                'rating' => $rating,
                'text' => $text,
                'date' => $date,
                'approved' => (bool)$approved
            ];
            echo json_encode(['success' => true, 'testimonial' => $testimonial]);
            break;

        case 'delete':
            $id = $input['id'] ?? null;
            if ($id) {
                $stmt = $db->prepare("DELETE FROM testimonials WHERE id = ?");
                $stmt->execute([$id]);
            }
            echo json_encode(['success' => true]);
            break;

        case 'toggle_approval':
            $id = $input['id'] ?? null;
            if ($id) {
                $stmt = $db->prepare("UPDATE testimonials SET approved = 1 - approved WHERE id = ?");
                $stmt->execute([$id]);
            }
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
