<?php
// admin/api/status.php
header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'php_version' => phpversion(),
    'writable_data' => is_writable('../../customers/data/users.js')
]);
