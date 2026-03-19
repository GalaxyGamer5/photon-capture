<?php
/**
 * Create Order API Endpoint
 * 
 * Creates new orders in orders.json when bookings are submitted
 * Called automatically by the booking form after email is sent
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust in production
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Read and validate input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

// Validate required fields
$required = ['orderId', 'name', 'email', 'service', 'totalAmount'];
foreach ($required as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
        exit;
    }
}

// Sanitize and prepare order data
$order = [
    'orderId' => sanitize($data['orderId']),
    'customerName' => sanitize($data['name']),
    'email' => filter_var($data['email'], FILTER_SANITIZE_EMAIL),
    'phone' => isset($data['phone']) ? sanitize($data['phone']) : '',
    'bookingDate' => isset($data['date']) ? sanitize($data['date']) : '',
    'service' => sanitize($data['service']),
    'duration' => isset($data['duration']) ? intval($data['duration']) : 0,
    'extras' => isset($data['extras']) && is_array($data['extras']) ? array_map('sanitize', $data['extras']) : [],
    'totalAmount' => floatval($data['totalAmount']),
    'depositAmount' => round(floatval($data['totalAmount']) * 0.1, 2), // 10% deposit
    'depositPaid' => false,
    'depositPaidDate' => null,
    'depositPaymentMethod' => null,
    'finalPaymentUnlocked' => false,
    'finalAmount' => round(floatval($data['totalAmount']) * 0.9, 2), // Remaining 90%
    'finalPaid' => false,
    'finalPaidDate' => null,
    'finalPaymentMethod' => null,
    'created' => date('c'), // ISO 8601 format
    'notes' => isset($data['notes']) ? sanitize($data['notes']) : ''
];

// Validate email
if (!filter_var($order['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid email address']);
    exit;
}

// Path to orders.json
$ordersFile = __DIR__ . '/../data/orders.json';

// Ensure data directory exists
if (!file_exists(dirname($ordersFile))) {
    mkdir(dirname($ordersFile), 0755, true);
}

// Initialize orders.json if it doesn't exist
if (!file_exists($ordersFile)) {
    file_put_contents($ordersFile, json_encode(['orders' => []], JSON_PRETTY_PRINT));
}

// Lock file for concurrent access safety
$lockFile = $ordersFile . '.lock';
$lock = fopen($lockFile, 'w');
if (!flock($lock, LOCK_EX)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not acquire lock']);
    fclose($lock);
    exit;
}

try {
    // Read existing orders
    $ordersJson = file_get_contents($ordersFile);
    $ordersData = json_decode($ordersJson, true);
    
    if (!$ordersData || !isset($ordersData['orders'])) {
        $ordersData = ['orders' => []];
    }
    
    // Check if order ID already exists
    foreach ($ordersData['orders'] as $existingOrder) {
        if ($existingOrder['orderId'] === $order['orderId']) {
            // Order already exists, return success (idempotent)
            flock($lock, LOCK_UN);
            fclose($lock);
            
            echo json_encode([
                'success' => true,
                'message' => 'Order already exists',
                'orderId' => $order['orderId'],
                'paymentUrl' => getPaymentUrl($order['orderId'])
            ]);
            exit;
        }
    }
    
    // Add new order
    $ordersData['orders'][] = $order;
    
    // Write back to file
    $result = file_put_contents($ordersFile, json_encode($ordersData, JSON_PRETTY_PRINT));
    
    if ($result === false) {
        throw new Exception('Failed to write orders file');
    }
    
    // Release lock
    flock($lock, LOCK_UN);
    fclose($lock);
    
    // Return success response
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Order created successfully',
        'orderId' => $order['orderId'],
        'depositAmount' => $order['depositAmount'],
        'paymentUrl' => getPaymentUrl($order['orderId'])
    ]);
    
} catch (Exception $e) {
    // Release lock on error
    flock($lock, LOCK_UN);
    fclose($lock);
    
    // Log error
    error_log('Order creation error: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to create order: ' . $e->getMessage()
    ]);
}

/**
 * Sanitize input string
 */
function sanitize($str) {
    return htmlspecialchars(strip_tags(trim($str)), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate payment URL for an order
 */
function getPaymentUrl($orderId) {
    // Adjust domain based on your setup
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // If you're using subdomains:
    // return "$protocol://payment.$host/order.html?id=$orderId";
    
    // If using single domain with folders:
    return "$protocol://$host/payment/order.html?id=$orderId";
}
