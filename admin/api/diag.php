<?php
// admin/api/diag.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$response = [
    'status' => 'ok',
    'method' => $_SERVER['REQUEST_METHOD'],
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'php_version' => phpversion(),
    'script_filename' => $_SERVER['SCRIPT_FILENAME'],
    'cwd' => getcwd(),
    'post_data_received' => file_get_contents('php://input'),
    'headers' => getallheaders()
];

echo json_encode($response, JSON_PRETTY_PRINT);
