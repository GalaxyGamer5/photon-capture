<?php
// admin/api/auth.php
require_once 'config.php';

session_start();

// Handle POST request (Login)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $password = $data['password'] ?? '';

    // Verify password
    // For now, using a simple check since we couldn't generate the hash in the environment.
    // In production, uncomment the password_verify line and set the correct hash in config.php
    if ($password === 'photon2024') { // || password_verify($password, ADMIN_PASSWORD_HASH)) {
        $_SESSION['admin_logged_in'] = true;
        jsonResponse(['success' => true]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Invalid password'], 401);
    }
}

// Handle GET request (Check status or Logout)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['action']) && $_GET['action'] === 'logout') {
        session_destroy();
        jsonResponse(['success' => true]);
    }

    // Check login status
    $isLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    jsonResponse(['loggedIn' => $isLoggedIn]);
}
?>
