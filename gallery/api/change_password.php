<?php
// customers/api/change_password.php
header('Content-Type: application/json');

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['userId']) || !isset($data['oldPasswordHash']) || !isset($data['newPasswordHash'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$userId = $data['userId'];
$oldHash = $data['oldPasswordHash'];
$newHash = $data['newPasswordHash'];

$usersFile = '../data/users.js';

if (!file_exists($usersFile)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database not found']);
    exit;
}

// Read the JS file
$content = file_get_contents($usersFile);

// Extract JSON part using regex (matches window.usersDatabase = { ... };)
if (preg_match('/window\.usersDatabase\s*=\s*({[\s\S]*?});/', $content, $matches)) {
    $jsonStr = $matches[1];
    $userData = json_decode($jsonStr, true);

    if ($userData === null) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database corruption']);
        exit;
    }

    // Find user and verify old password
    $userFound = false;
    foreach ($userData['users'] as &$user) {
        if ($user['id'] === $userId) {
            $userFound = true;
            if ($user['passwordHash'] === $oldHash) {
                // Update password
                $user['passwordHash'] = $newHash;
                
                // Save back to file
                $newJsonStr = json_encode($userData, JSON_PRETTY_PRINT);
                $newContent = "window.usersDatabase = " . $newJsonStr . ";";
                
                if (file_put_contents($usersFile, $newContent)) {
                    echo json_encode(['success' => true]);
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Write error']);
                }
                exit;
            } else {
                echo json_encode(['success' => false, 'message' => 'Old password incorrect']);
                exit;
            }
        }
    }

    if (!$userFound) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }

} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database format error']);
}
