<?php
// admin/api/get_users.php
header('Content-Type: application/javascript');

// Path to data file
$file = '../../customers/data/users.js';

if (!file_exists($file)) {
    http_response_code(404);
    echo "console.error('users.js not found');";
    exit;
}

// Check permissions (optional, but good for debugging)
if (!is_readable($file)) {
    http_response_code(500);
    echo "console.error('Permission denied: users.js is not readable');";
    exit;
}

// Output file content
readfile($file);
?>
