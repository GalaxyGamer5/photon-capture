<?php
/**
 * admin/api/phpinfo-check.php
 * Quick FPM config checker — DELETE THIS FILE after verifying!
 * Access via browser: https://yoursite.com/admin/api/phpinfo-check.php
 */

// Basic admin auth check
if (session_status() === PHP_SESSION_NONE) session_start();
// Uncomment if you want to restrict to logged-in admins:
// if (sessionStorage is not applicable in PHP; rely on obscurity + delete after use)

header('Content-Type: text/plain');

$keys = [
    'max_file_uploads',
    'upload_max_filesize',
    'post_max_size',
    'max_execution_time',
    'max_input_time',
    'memory_limit',
];

echo "=== PHP-FPM Active Configuration ===" . PHP_EOL;
echo "SAPI: " . php_sapi_name() . PHP_EOL;
echo "php.ini: " . php_ini_loaded_file() . PHP_EOL;
echo PHP_EOL;

foreach ($keys as $key) {
    echo str_pad($key . ':', 25) . ini_get($key) . PHP_EOL;
}

echo PHP_EOL;
echo "=== DELETE THIS FILE WHEN DONE ===" . PHP_EOL;
