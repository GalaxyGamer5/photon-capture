<?php
// admin/api/orders.php
require_once 'config.php';

// Path to orders.json (Relative to admin/api/ -> ../../payment/data/orders.json)
$ordersFile = __DIR__ . '/../../payment/data/orders.json';

// Handle GET (Fetch Orders)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (file_exists($ordersFile)) {
        header('Content-Type: application/json');
        readfile($ordersFile);
    } else {
        echo json_encode(['orders' => []]);
    }
    exit;
}

// Handle POST (Update Order)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['orderId']) || !isset($data['updates'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing parameters']);
        exit;
    }

    $orderId = $data['orderId'];
    $updates = $data['updates'];

    if (!file_exists($ordersFile)) {
        http_response_code(404);
        echo json_encode(['error' => 'Orders file not found']);
        exit;
    }

    $ordersData = json_decode(file_get_contents($ordersFile), true);
    $updated = false;

    foreach ($ordersData['orders'] as &$order) {
        if ($order['orderId'] === $orderId) {
            foreach ($updates as $key => $value) {
                $order[$key] = $value;
            }
            $updated = true;
            break;
        }
    }

    if ($updated) {
        file_put_contents($ordersFile, json_encode($ordersData, JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found']);
    }
    exit;
}
?>
