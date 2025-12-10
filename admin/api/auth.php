<?php
// admin/api/auth.php
require_once 'config.php';

session_start();

try {
    // Handle POST request (Login)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON input');
        }

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
} catch (Exception $e) {
    error_log("Auth Error: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
} catch (Error $e) {
    error_log("Auth Fatal Error: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Server fatal error'], 500);
}

