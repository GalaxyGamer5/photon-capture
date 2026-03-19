<?php
/**
 * Stripe Webhook Handler
 * 
 * This endpoint receives webhook events from Stripe to confirm payments
 */

define('PAYMENT_API', true);
require_once 'config.php';

// Load Stripe library
require_once 'vendor/autoload.php';
\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

// Log file for debugging (remove in production or secure properly)
$logFile = dirname(__DIR__) . '/logs/webhook.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

function logWebhook($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND);
}

try {
    // Get the raw POST body
    $payload = @file_get_contents('php://input');
    $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
    
    logWebhook("Webhook received. Signature: " . substr($sigHeader, 0, 20) . "...");
    
    // Verify webhook signature
    try {
        $event = \Stripe\Webhook::constructEvent(
            $payload,
            $sigHeader,
            STRIPE_WEBHOOK_SECRET
        );
    } catch (\UnexpectedValueException $e) {
        // Invalid payload
        logWebhook("Invalid payload: " . $e->getMessage());
        http_response_code(400);
        exit();
    } catch (\Stripe\Exception\SignatureVerificationException $e) {
        // Invalid signature
        logWebhook("Invalid signature: " . $e->getMessage());
        http_response_code(400);
        exit();
    }
    
    logWebhook("Event type: " . $event->type);
    
    // Handle the event
    switch ($event->type) {
        case 'checkout.session.completed':
            $session = $event->data->object;
            
            // Get metadata
            $orderId = $session->metadata->orderId ?? null;
            $paymentType = $session->metadata->paymentType ?? null;
            
            logWebhook("Payment completed for order: {$orderId}, type: {$paymentType}");
            
            if (!$orderId || !$paymentType) {
                logWebhook("Missing metadata");
                break;
            }
            
            // Load order
            $order = findOrder($orderId);
            if (!$order) {
                logWebhook("Order not found: {$orderId}");
                break;
            }
            
            // Update order based on payment type
            $updates = [];
            
            if ($paymentType === 'deposit') {
                $updates = [
                    'depositPaid' => true,
                    'depositPaidDate' => date('c'),
                    'depositPaymentMethod' => 'Stripe',
                    'depositStripeSessionId' => $session->id,
                ];
                logWebhook("Marking deposit as paid for order: {$orderId}");
            } elseif ($paymentType === 'final') {
                $updates = [
                    'finalPaid' => true,
                    'finalPaidDate' => date('c'),
                    'finalPaymentMethod' => 'Stripe',
                    'finalStripeSessionId' => $session->id,
                ];
                logWebhook("Marking final payment as paid for order: {$orderId}");
            }
            
            if (!empty($updates)) {
                $success = updateOrder($orderId, $updates);
                if ($success) {
                    logWebhook("Order updated successfully");
                } else {
                    logWebhook("Failed to update order");
                }
            }
            
            break;
            
        default:
            logWebhook("Unhandled event type: " . $event->type);
    }
    
    // Return 200 to acknowledge receipt
    http_response_code(200);
    echo json_encode(['received' => true]);
    
} catch (Exception $e) {
    logWebhook("Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
