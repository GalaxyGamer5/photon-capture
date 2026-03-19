<?php
/**
 * Create Stripe Checkout Session
 * 
 * This endpoint creates a Stripe Checkout session for payment processing
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

// Load Stripe library
require_once 'vendor/autoload.php';
\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

try {
    // Get request data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Invalid request data');
    }
    
    $orderId = $data['orderId'] ?? null;
    $paymentType = $data['paymentType'] ?? null; // 'deposit' or 'final'
    
    if (!$orderId || !$paymentType) {
        throw new Exception('Missing required parameters');
    }
    
    // Load order
    $order = findOrder($orderId);
    if (!$order) {
        throw new Exception('Order not found');
    }
    
    // Determine amount based on payment type
    if ($paymentType === 'deposit') {
        $amount = $order['depositAmount'];
        $description = "Anzahlung für Bestellung {$orderId}";
        
        // Check if already paid
        if ($order['depositPaid']) {
            throw new Exception('Deposit already paid');
        }
    } elseif ($paymentType === 'final') {
        $amount = $order['finalAmount'];
        $description = "Restzahlung für Bestellung {$orderId}";
        
        // Check if unlocked and not already paid
        if (!$order['finalPaymentUnlocked']) {
            throw new Exception('Final payment not yet unlocked');
        }
        if ($order['finalPaid']) {
            throw new Exception('Final payment already made');
        }
    } else {
        throw new Exception('Invalid payment type');
    }
    
    // Convert amount to cents (Stripe uses smallest currency unit)
    $amountCents = (int)($amount * 100);
    
    // Create Stripe Checkout Session
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => CURRENCY,
                'product_data' => [
                    'name' => $description,
                    'description' => "{$order['service']} - {$order['customerName']}",
                ],
                'unit_amount' => $amountCents,
            ],
            'quantity' => 1,
        ]],
        'mode' => 'payment',
        'success_url' => str_replace('{CHECKOUT_SESSION_ID}', '{CHECKOUT_SESSION_ID}', SUCCESS_URL),
        'cancel_url' => CANCEL_URL . '&id=' . urlencode($orderId),
        'metadata' => [
            'orderId' => $orderId,
            'paymentType' => $paymentType,
            'customerEmail' => $order['email'] ?? '',
        ],
        'customer_email' => $order['email'] ?? null,
    ]);
    
    // Return session data
    echo json_encode([
        'success' => true,
        'sessionId' => $session->id,
        'url' => $session->url,
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
