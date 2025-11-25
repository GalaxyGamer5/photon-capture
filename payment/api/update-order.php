<?php
/**
 * Update Order API
 * 
 * Updates an existing order
 */

define('PAYMENT_API', true);
require_once 'config.php';

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    // Get request data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Invalid request data');
    }
    
    $orderId = $data['orderId'] ?? null;
    $updates = $data['updates'] ?? [];
    
    if (!$orderId || empty($updates)) {
        throw new Exception('Missing required parameters');
    }
    
    // Update order
    $success = updateOrder($orderId, $updates);
    
    if (!$success) {
        throw new Exception('Failed to update order');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Order updated successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
