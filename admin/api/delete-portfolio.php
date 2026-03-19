<?php
session_start();
header('Content-Type: application/json');



$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing image ID']);
    exit;
}

$id = $data['id'];
$portfolioFile = __DIR__ . '/../../data/portfolio.json';

if (!file_exists($portfolioFile)) {
    echo json_encode(['success' => false, 'error' => 'Database not found']);
    exit;
}

$portfolioData = json_decode(file_get_contents($portfolioFile), true);
$images = $portfolioData['images'] ?? [];
$found = false;
$filename = '';

foreach ($images as $index => $image) {
    if ($image['id'] === $id) {
        $found = true;
        $filename = $image['filename'];
        array_splice($portfolioData['images'], $index, 1);
        break;
    }
}

if (!$found) {
    echo json_encode(['success' => false, 'error' => 'Image not found']);
    exit;
}

// Delete file
$targetPath = '../../assets/portfolio/' . $filename;
if (file_exists($targetPath)) {
    unlink($targetPath);
}

// Save json
if (file_put_contents($portfolioFile, json_encode($portfolioData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    chmod($portfolioFile, 0664);
}

echo json_encode(['success' => true]);
