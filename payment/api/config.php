<?php
/**
 * Stripe Payment Configuration
 * 
 * SECURITY NOTE: Keep this file secure and never commit API keys to version control
 */

// Prevent direct access
if (!defined('PAYMENT_API')) {
    http_response_code(403);
    exit('Access denied');
}

// Enable error reporting for development (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Set to '0' in production
ini_set('log_errors', '1');

// Stripe API Keys
// Get these from: https://dashboard.stripe.com/apikeys
define('STRIPE_TEST_SECRET_KEY', 'sk_test_YOUR_TEST_SECRET_KEY_HERE');
define('STRIPE_LIVE_SECRET_KEY', 'sk_live_YOUR_LIVE_SECRET_KEY_HERE');

// Stripe Publishable Keys (for frontend)
define('STRIPE_TEST_PUBLISHABLE_KEY', 'pk_test_YOUR_TEST_PUBLISHABLE_KEY_HERE');
define('STRIPE_LIVE_PUBLISHABLE_KEY', 'pk_live_YOUR_LIVE_PUBLISHABLE_KEY_HERE');

// Webhook Secret
// Get this from: https://dashboard.stripe.com/webhooks
define('STRIPE_WEBHOOK_SECRET', 'whsec_YOUR_WEBHOOK_SECRET_HERE');

// Environment: 'test' or 'live'
define('STRIPE_MODE', 'test');

// Get appropriate keys based on mode
define('STRIPE_SECRET_KEY', STRIPE_MODE === 'live' ? STRIPE_LIVE_SECRET_KEY : STRIPE_TEST_SECRET_KEY);
define('STRIPE_PUBLISHABLE_KEY', STRIPE_MODE === 'live' ? STRIPE_LIVE_PUBLISHABLE_KEY : STRIPE_TEST_PUBLISHABLE_KEY);

// Payment configuration
define('DEPOSIT_AMOUNT', 50); // €50 deposit
define('CURRENCY', 'eur');

// Return URLs (update with your domain)
define('SUCCESS_URL', 'https://yourdomain.com/payment/success.html?session_id={CHECKOUT_SESSION_ID}');
define('CANCEL_URL', 'https://yourdomain.com/payment/order.html?canceled=1');

// Orders data file
define('ORDERS_FILE', dirname(__DIR__) . '/data/orders.json');

// Helper function to load orders
function loadOrders() {
    if (!file_exists(ORDERS_FILE)) {
        return ['orders' => []];
    }
    $json = file_get_contents(ORDERS_FILE);
    return json_decode($json, true) ?: ['orders' => []];
}

// Helper function to save orders
function saveOrders($data) {
    $json = json_encode($data, JSON_PRETTY_PRINT);
    return file_put_contents(ORDERS_FILE, $json) !== false;
}

// Helper function to find order by ID
function findOrder($orderId) {
    $data = loadOrders();
    foreach ($data['orders'] as $order) {
        if ($order['orderId'] === $orderId) {
            return $order;
        }
    }
    return null;
}

// Helper function to update order
function updateOrder($orderId, $updates) {
    $data = loadOrders();
    $found = false;
    
    foreach ($data['orders'] as &$order) {
        if ($order['orderId'] === $orderId) {
            $order = array_merge($order, $updates);
            $found = true;
            break;
        }
    }
    
    if ($found) {
        return saveOrders($data);
    }
    
    return false;
}
