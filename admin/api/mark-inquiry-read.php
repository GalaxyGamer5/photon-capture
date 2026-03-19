<?php
session_start();
header('Content-Type: application/json');



$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Missing ID']);
    exit;
}

$file = __DIR__ . '/../../data/inquiries.json';
if (!file_exists($file)) {
    echo json_encode(['success' => false, 'error' => 'Database not found']);
    exit;
}

$db = json_decode(file_get_contents($file), true) ?: ['inquiries' => []];

$found = false;
foreach ($db['inquiries'] as &$inq) {
    if ($inq['id'] === $id) {
        $inq['unread'] = false;
        $found = true;
        break;
    }
}
unset($inq);

if ($found && file_put_contents($file, json_encode($db, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to update']);
}
