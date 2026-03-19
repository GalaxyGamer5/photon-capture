<?php
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

$image = $input['image'];
$username = $_SESSION['user']['username'];
$file = __DIR__ . '/../data/favorites.json';

$data = [];
if (file_exists($file)) {
    $content = file_get_contents($file);
    $data = json_decode($content, true) ?: [];
}

if (!isset($data[$username])) {
    $data[$username] = [];
}

$index = array_search($image, $data[$username]);
$isFavorite = false;

if ($index !== false) {
    // Remove from favorites
    array_splice($data[$username], $index, 1);
} else {
    // Add to favorites
    $data[$username][] = $image;
    $isFavorite = true;
}

if (file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    chmod($file, 0664);
    echo json_encode(['success' => true, 'isFavorite' => $isFavorite, 'favorites' => $data[$username]]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save favorites']);
}
