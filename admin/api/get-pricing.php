<?php
// admin/api/get-pricing.php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../includes/db.php';

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT value FROM app_settings WHERE key_name = 'pricing' LIMIT 1");
    $stmt->execute();
    $val = $stmt->fetchColumn();

    if ($val) {
        echo $val;
    } else {
        $file = __DIR__ . '/../../data/pricing.json';
        if (file_exists($file)) {
            echo file_get_contents($file);
        } else {
            echo json_encode(['error' => 'Pricing file not found']);
        }
    }
} catch (Exception $e) {
    $file = __DIR__ . '/../../data/pricing.json';
    if (file_exists($file)) {
        echo file_get_contents($file);
    } else {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
