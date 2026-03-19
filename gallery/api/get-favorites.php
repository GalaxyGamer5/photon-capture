<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true || !isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$username = $_SESSION['user']['username'];
$file = '../data/favorites.json';

$favorites = [];
if (file_exists($file)) {
    $content = file_get_contents($file);
    $data = json_decode($content, true);
    if (is_array($data) && isset($data[$username])) {
        $favorites = $data[$username];
    }
}

echo json_encode(['success' => true, 'favorites' => $favorites]);
