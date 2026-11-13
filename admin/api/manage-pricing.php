<?php
// admin/api/manage-pricing.php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/pricing_utils.php';

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid or empty JSON received']);
    exit;
}

$jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

try {
    $db = getDB();
    $now = date('Y-m-d H:i:s');
    $stmt = $db->prepare("INSERT INTO app_settings (key_name, value, updated_at) VALUES ('pricing', ?, ?) ON CONFLICT(key_name) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at");
    $stmt->execute([$jsonData, $now]);

    // Also keep data/pricing.json in sync for fallback
    $file = __DIR__ . '/../../data/pricing.json';
    @file_put_contents($file, $jsonData);
    @chmod($file, 0777);

    // Recalculate orders based on new pricing
    $ordersFile = __DIR__ . '/../../data/orders.json';
    if (file_exists($ordersFile)) {
        $ordersDb = json_decode(file_get_contents($ordersFile), true);
        if ($ordersDb && isset($ordersDb['orders'])) {
            foreach ($ordersDb['orders'] as &$order) {
                $res = calculateOrderPrice($order, $data);
                $order['price'] = $res['price'];
                $order['originalPrice'] = $res['originalPrice'];
                $order['discountText'] = $res['discountText'];
            }
            $updatedOrdersJson = json_encode($ordersDb, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            @file_put_contents($ordersFile, $updatedOrdersJson);
            @chmod($ordersFile, 0777);
        }
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
