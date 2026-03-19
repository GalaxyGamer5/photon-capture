<?php
// Start session
session_start();

// Set JSON header
header('Content-Type: application/json');

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['username']) || !isset($input['password'])) {
    echo json_encode(['success' => false, 'error' => 'Username and password required']);
    exit;
}

$username = $input['username'];
$password = $input['password'];

// Load users database
$usersFile = __DIR__ . '/../data/users.js';
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

// Hash the provided password with SHA-1 (matching client-side implementation)
$passwordHash = sha1($password);

// Find user
$authenticatedUser = null;
foreach ($usersData['users'] as $user) {
    if ($user['username'] === $username && $user['passwordHash'] === $passwordHash) {
        $authenticatedUser = $user;
        break;
    }
}

if ($authenticatedUser) {
    // Store user info in session (excluding password hash)
    $_SESSION['authenticated'] = true;
    $_SESSION['user'] = [
        'id' => $authenticatedUser['id'],
        'username' => $authenticatedUser['username'],
        'name' => $authenticatedUser['name'],
        'folder' => $authenticatedUser['folder'],
        'imageCount' => $authenticatedUser['imageCount']
    ];
    
    echo json_encode([
        'success' => true,
        'user' => $_SESSION['user']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid username or password'
    ]);
}
