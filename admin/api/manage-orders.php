<?php
// admin/api/manage-orders.php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 0);

$file = __DIR__ . '/../../data/orders.json';

// Helper: Load
function loadOrders($path) {
    if (!file_exists($path)) {
        return ['orders' => []];
    }
    $json = file_get_contents($path);
    return json_decode($json, true) ?: ['orders' => []];
}

// Helper: Save
function saveOrders($path, $data) {
    $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (!$jsonData) return false;

    // If file exists and is not writable, TRY deleting it (requires directory write permission)
    if (file_exists($path) && !is_writable($path)) {
        @unlink($path);
    }

    $res = file_put_contents($path, $jsonData);
    if ($res !== false) {
        @chmod($path, 0777);
        return true;
    }
    return false;
}

require_once __DIR__ . '/pricing_utils.php';
$pricingFile = __DIR__ . '/../../data/pricing.json';
$pricing = json_decode(file_get_contents($pricingFile), true);

// ── GET Action ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'list') {
        $db = loadOrders($file);
        echo json_encode(['success' => true, 'orders' => $db['orders']]);
        exit;
    }
}

// ── POST Action ───────────────────────────────────────────────────────
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if (!$action) {
    echo json_encode(['success' => false, 'error' => 'No action specified']);
    exit;
}

$db = loadOrders($file);
$success = false;
$error = 'Unknown action';

switch ($action) {
    case 'create':
        $newOrder = [
            'id' => 'ORD-' . date('Ymd-His') . '-' . substr(uniqid(), -4),
            'createdAt' => date('c'),
            'clientName' => $input['clientName'] ?? 'Unbekannt',
            'package' => $input['package'] ?? '',
            'packageLabel' => $input['packageLabel'] ?? '',
            'status' => 'neu',
            'notes' => $input['notes'] ?? '',
            'selectedExtras' => $input['selectedExtras'] ?? [],
            'discount' => $input['discount'] ?? ['value' => 0, 'type' => 'euro'],
            'hours' => $input['hours'] ?? null
        ];
        $res = calculateOrderPrice($newOrder, $pricing);
        $newOrder['price'] = $res['price'];
        $newOrder['originalPrice'] = $res['originalPrice'];
        $newOrder['discountText'] = $res['discountText'];
        array_unshift($db['orders'], $newOrder);
        $success = saveOrders($file, $db);
        break;

    case 'update':
        $id = $input['id'] ?? '';
        foreach ($db['orders'] as &$o) {
            if ($o['id'] === $id) {
                $o['clientName'] = $input['clientName'] ?? $o['clientName'];
                $o['package'] = $input['package'] ?? $o['package'];
                $o['packageLabel'] = $input['packageLabel'] ?? $o['packageLabel'];
                $o['notes'] = $input['notes'] ?? $o['notes'];
                $o['selectedExtras'] = $input['selectedExtras'] ?? ($o['selectedExtras'] ?? []);
                $o['discount'] = $input['discount'] ?? ($o['discount'] ?? ['value' => 0, 'type' => 'euro']);
                $o['hours'] = $input['hours'] ?? ($o['hours'] ?? null);
                
                // Recalculate price on update
                $res = calculateOrderPrice($o, $pricing);
                $o['price'] = $res['price'];
                $o['originalPrice'] = $res['originalPrice'];
                $o['discountText'] = $res['discountText'];
                
                $success = true;
                break;
            }
        }
        if ($success) $success = saveOrders($file, $db);
        else $error = 'Order not found';
        break;

    case 'delete':
        $id = $input['id'] ?? '';
        $count = count($db['orders']);
        $db['orders'] = array_filter($db['orders'], function($o) use ($id) {
            return $o['id'] !== $id;
        });
        $db['orders'] = array_values($db['orders']); // re-index
        if (count($db['orders']) < $count) {
            $success = saveOrders($file, $db);
        } else {
            $error = 'Order not found';
        }
        break;

    case 'set_status':
        $id = $input['id'] ?? '';
        $status = $input['status'] ?? '';
        foreach ($db['orders'] as &$o) {
            if ($o['id'] === $id) {
                $o['status'] = $status;
                $success = true;
                break;
            }
        }
        if ($success) $success = saveOrders($file, $db);
        else $error = 'Order not found';
        break;
}

echo json_encode(['success' => $success, 'error' => $success ? null : $error]);
