<?php
// admin/api/delete-portfolio.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing image ID']);
    exit;
}

$id = $data['id'];

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT filename FROM portfolio WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    $filename = $row ? $row['filename'] : '';

    // Delete record from SQL database
    $del = $db->prepare("DELETE FROM portfolio WHERE id = ?");
    $del->execute([$id]);

    // If not found in DB, check legacy JSON
    $portfolioFile = __DIR__ . '/../../data/portfolio.json';
    if (file_exists($portfolioFile)) {
        $portfolioData = json_decode(file_get_contents($portfolioFile), true) ?: ['images' => []];
        $images = $portfolioData['images'] ?? [];
        foreach ($images as $index => $image) {
            if ($image['id'] === $id) {
                if (!$filename) $filename = $image['filename'];
                array_splice($portfolioData['images'], $index, 1);
                break;
            }
        }
        @file_put_contents($portfolioFile, json_encode($portfolioData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    // Delete file from disk
    if ($filename) {
        $targetPath = __DIR__ . '/../../assets/portfolio/' . $filename;
        if (file_exists($targetPath)) {
            @unlink($targetPath);
        }
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
