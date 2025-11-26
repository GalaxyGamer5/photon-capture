<?php
/**
 * Create Order from Booking
 * 
 * This endpoint creates a new order when a booking is submitted
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
    
    // Extract booking data
    $orderId = $data['orderId'] ?? null;
    $customerName = $data['name'] ?? '';
    $email = $data['email'] ?? '';
    $service = $data['service'] ?? '';
    $bookingDate = $data['date'] ?? '';
    $totalAmount = $data['totalAmount'] ?? 0;
    $extras = $data['extras'] ?? [];
    $duration = $data['duration'] ?? 0;
    
    if (!$orderId || !$customerName || !$email || !$totalAmount) {
        throw new Exception('Missing required booking information');
    }
    
    // Check if order already exists
    $existingOrder = findOrder($orderId);
    if ($existingOrder) {
        // Order already exists, just return success
        echo json_encode([
            'success' => true,
            'message' => 'Order already exists',
            'orderId' => $orderId
        ]);
        exit();
    }
    
    // Create new order
    $depositAmount = DEPOSIT_AMOUNT; // 50€
    $finalAmount = max(0, $totalAmount - $depositAmount);
    
    $newOrder = [
        'orderId' => $orderId,
        'customerName' => $customerName,
        'email' => $email,
        'phone' => $data['phone'] ?? '',
        'bookingDate' => $bookingDate,
        'service' => $service,
        'duration' => $duration,
        'extras' => $extras,
        'totalAmount' => $totalAmount,
        'depositAmount' => $depositAmount,
        'depositPaid' => false,
        'depositPaidDate' => null,
        'depositPaymentMethod' => null,
        'finalPaymentUnlocked' => false,
        'finalAmount' => $finalAmount,
        'finalPaid' => false,
        'finalPaidDate' => null,
        'finalPaymentMethod' => null,
        'created' => date('c'),
        'notes' => ''
    ];
    
    // Load existing orders
    $ordersData = loadOrders();
    
    // Add new order
    $ordersData['orders'][] = $newOrder;
    
    // Save orders
    if (!saveOrders($ordersData)) {
        throw new Exception('Failed to save order');
    }
    
    // Return success
    echo json_encode([
        'success' => true,
        'message' => 'Order created successfully',
        'orderId' => $orderId,
        'depositAmount' => $depositAmount,
        'finalAmount' => $finalAmount
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
