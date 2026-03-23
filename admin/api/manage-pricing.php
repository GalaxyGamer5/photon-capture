<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0); // Hide from HTML output

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid or empty JSON received: ' . $input]);
    exit;
}

$file = __DIR__ . '/../../data/pricing.json';

$jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

if (!$jsonData) {
    echo json_encode(['success' => false, 'error' => 'JSON Encoding failed: ' . json_last_error_msg()]);
    exit;
}

// If file exists and is not writable, TRY deleting it (requires directory write permission)
if (file_exists($file) && !is_writable($file)) {
    @unlink($file);
}

if (file_put_contents($file, $jsonData)) {
    @chmod($file, 0777); // Ensure it stays writable
    
    // Recalculate ALL orders based on new pricing
    require_once __DIR__ . '/pricing_utils.php';
    $ordersFile = __DIR__ . '/../../data/orders.json';
    if (file_exists($ordersFile)) {
        $ordersDb = json_decode(file_get_contents($ordersFile), true);
        if ($ordersDb && isset($ordersDb['orders'])) {
            foreach ($ordersDb['orders'] as &$order) {
                // Update price using new pricing $data
                $order['price'] = calculateOrderPrice($order, $data);
            }
            // Save orders back
            $updatedOrdersJson = json_encode($ordersDb, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            file_put_contents($ordersFile, $updatedOrdersJson);
            @chmod($ordersFile, 0777);
        }
    }

    echo json_encode(['success' => true]);
} else {
    $error = error_get_last();
    echo json_encode(['success' => false, 'error' => 'Failed to write to file: ' . ($error['message'] ?? 'Unknown error')]);
}
