<?php
/**
 * Orders Management API
 * Actions: list, create, update, delete, next_status
 */
session_start();
header('Content-Type: application/json');

$ordersFile = __DIR__ . '/../../data/orders.json';

// --- Helper: read orders ---
function readOrders($file) {
    if (!file_exists($file)) {
        return ['orders' => []];
    }
    $data = json_decode(file_get_contents($file), true);
    if (!is_array($data) || !isset($data['orders'])) {
        return ['orders' => []];
    }
    return $data;
}

// --- Helper: write orders ---
function writeOrders($file, $data) {
    $result = file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    if ($result !== false) {
        chmod($file, 0664);
        return true;
    }
    return false;
}

// --- Helper: generate next order ID ---
function generateOrderId($orders) {
    $year = date('Y');
    $max = 0;
    foreach ($orders as $order) {
        if (preg_match('/^ORD-' . $year . '-(\d+)$/', $order['id'], $m)) {
            $max = max($max, (int)$m[1]);
        }
    }
    return 'ORD-' . $year . '-' . str_pad($max + 1, 3, '0', STR_PAD_LEFT);
}

// Status progression
$statusFlow = ['neu', 'shooting_fertig', 'geliefert', 'abgeschlossen'];

// --- Read action ---
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? ($_GET['action'] ?? 'list');

$db = readOrders($ordersFile);
$orders = &$db['orders'];

switch ($action) {

    // ── LIST ──────────────────────────────────────
    case 'list':
        // Sort newest first
        usort($orders, function($a, $b) {
            return strcmp($b['createdAt'], $a['createdAt']);
        });
        echo json_encode(['success' => true, 'orders' => $orders]);
        break;

    // ── CREATE ────────────────────────────────────
    case 'create':
        $newOrder = [
            'id'           => generateOrderId($orders),
            'createdAt'    => date('c'),
            'clientName'   => trim($input['clientName'] ?? ''),
            'package'      => trim($input['package'] ?? ''),
            'packageLabel' => trim($input['packageLabel'] ?? $input['package'] ?? ''),
            'price'        => trim($input['price'] ?? ''),
            'notes'        => trim($input['notes'] ?? ''),
            'status'       => 'neu',
        ];

        if (empty($newOrder['clientName']) || empty($newOrder['package'])) {
            echo json_encode(['success' => false, 'error' => 'Name and package required']);
            exit;
        }

        $orders[] = $newOrder;
        if (writeOrders($ordersFile, $db)) {
            echo json_encode(['success' => true, 'order' => $newOrder]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Could not write file']);
        }
        break;

    // ── UPDATE ────────────────────────────────────
    case 'update':
        $id = trim($input['id'] ?? '');
        $found = false;
        foreach ($orders as &$order) {
            if ($order['id'] === $id) {
                if (isset($input['clientName']))   $order['clientName']   = trim($input['clientName']);
                if (isset($input['package']))      $order['package']      = trim($input['package']);
                if (isset($input['packageLabel'])) $order['packageLabel'] = trim($input['packageLabel']);
                if (isset($input['price']))        $order['price']        = trim($input['price']);
                if (isset($input['notes']))        $order['notes']        = trim($input['notes']);
                if (isset($input['status']))       $order['status']       = trim($input['status']);
                $found = true;
                break;
            }
        }
        unset($order);
        if (!$found) {
            echo json_encode(['success' => false, 'error' => 'Order not found']);
            exit;
        }
        if (writeOrders($ordersFile, $db)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Could not write file']);
        }
        break;

    // ── DELETE ────────────────────────────────────
    case 'delete':
        $id = trim($input['id'] ?? '');
        $before = count($orders);
        $orders = array_values(array_filter($orders, fn($o) => $o['id'] !== $id));
        if (count($orders) === $before) {
            echo json_encode(['success' => false, 'error' => 'Order not found']);
            exit;
        }
        $db['orders'] = $orders;
        if (writeOrders($ordersFile, $db)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Could not write file']);
        }
        break;

    // ── NEXT STATUS ────────────────────────────────
    case 'next_status':
        $id = trim($input['id'] ?? '');
        $found = false;
        foreach ($orders as &$order) {
            if ($order['id'] === $id) {
                $currentIndex = array_search($order['status'], $statusFlow);
                if ($currentIndex !== false && $currentIndex < count($statusFlow) - 1) {
                    $order['status'] = $statusFlow[$currentIndex + 1];
                }
                $found = true;
                break;
            }
        }
        unset($order);
        if (!$found) {
            echo json_encode(['success' => false, 'error' => 'Order not found']);
            exit;
        }
        if (writeOrders($ordersFile, $db)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Could not write file']);
        }
        break;

    // ── SET STATUS ─────────────────────────────────
    case 'set_status':
        $id     = trim($input['id'] ?? '');
        $status = trim($input['status'] ?? '');
        if (!in_array($status, $statusFlow)) {
            echo json_encode(['success' => false, 'error' => 'Invalid status']);
            exit;
        }
        $found = false;
        foreach ($orders as &$order) {
            if ($order['id'] === $id) {
                $order['status'] = $status;
                $found = true;
                break;
            }
        }
        unset($order);
        if (!$found) {
            echo json_encode(['success' => false, 'error' => 'Order not found']);
            exit;
        }
        if (writeOrders($ordersFile, $db)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Could not write file']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
