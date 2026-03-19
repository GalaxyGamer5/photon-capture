<?php
// Start session
session_start();

// Set JSON header
header('Content-Type: application/json');

// Destroy session
session_destroy();

echo json_encode(['success' => true]);
