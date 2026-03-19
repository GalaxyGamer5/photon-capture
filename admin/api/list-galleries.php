<?php
// Start session for admin auth
session_start();

// Check admin authentication


// Set JSON header
header('Content-Type: application/json');

// Load users database
$usersFile = __DIR__ . '/../../gallery/data/users.js';
if (!file_exists($usersFile)) {
    echo json_encode(['success' => false, 'error' => 'User database not found']);
    exit;
}

$usersContent = file_get_contents($usersFile);
// Extract JSON from "window.usersDatabase = {...};"
preg_match('/window\.usersDatabase\s*=\s*({[\s\S]*?});/', $usersContent, $matches);
if (!isset($matches[1])) {
    echo json_encode(['success' => false, 'error' => 'Invalid user database format']);
    exit;
}

$usersData = json_decode($matches[1], true);
if (!$usersData || !isset($usersData['users'])) {
    echo json_encode(['success' => false, 'error' => 'Failed to parse user database']);
    exit;
}

// Format gallery list
$galleries = [];
foreach ($usersData['users'] as $user) {
    $galleries[] = [
        'id' => $user['id'],
        'name' => $user['name'],
        'folder' => $user['folder'],
        'imageCount' => $user['imageCount'] ?? 0
    ];
}

echo json_encode([
    'success' => true,
    'galleries' => $galleries
]);
