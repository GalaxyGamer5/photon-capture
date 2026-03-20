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
    return file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
}

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
            'price' => $input['price'] ?? '0€',
            'status' => 'neu',
            'notes' => $input['notes'] ?? '',
            'selectedExtras' => $input['selectedExtras'] ?? [],
            'discount' => $input['discount'] ?? ['value' => 0, 'type' => 'euro'],
            'hours' => $input['hours'] ?? null
        ];
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
                $o['price'] = $input['price'] ?? $o['price'];
                $o['notes'] = $input['notes'] ?? $o['notes'];
                $o['selectedExtras'] = $input['selectedExtras'] ?? ($o['selectedExtras'] ?? []);
                $o['discount'] = $input['discount'] ?? ($o['discount'] ?? ['value' => 0, 'type' => 'euro']);
                $o['hours'] = $input['hours'] ?? ($o['hours'] ?? null);
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
