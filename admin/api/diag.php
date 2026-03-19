<?php
// admin/api/diag.php
header('Content-Type: application/json');

function get_perms($path) {
    if (!file_exists($path)) return 'N/A';
    return substr(sprintf('%o', fileperms($path)), -4);
}

function get_owner($path) {
    if (!file_exists($path)) return 'N/A';
    $owner = posix_getpwuid(fileowner($path));
    return $owner ? $owner['name'] : 'unknown';
}

function get_group($path) {
    if (!file_exists($path)) return 'N/A';
    $group = posix_getgrgid(filegroup($path));
    return $group ? $group['name'] : 'unknown';
}

$targets = [
    'root' => __DIR__ . '/../../',
    'data_dir' => __DIR__ . '/../../data/',
    'pricing_json' => __DIR__ . '/../../data/pricing.json',
    'gallery_data_dir' => __DIR__ . '/../../gallery/data/',
    'users_js' => __DIR__ . '/../../gallery/data/users.js',
    'gallery_assets' => __DIR__ . '/../../gallery/assets/',
    'portfolio_assets' => __DIR__ . '/../../assets/portfolio/'
];

$diag = [];
foreach ($targets as $name => $path) {
    $diag[$name] = [
        'path' => realpath($path) ?: $path,
        'exists' => file_exists($path),
        'writable' => is_writable($path),
        'readable' => is_readable($path),
        'perms' => get_perms($path),
        'owner' => get_owner($path),
        'group' => get_group($path)
    ];
}

echo json_encode([
    'php_user' => posix_getpwuid(posix_geteuid())['name'],
    'server_software' => $_SERVER['SERVER_SOFTWARE'],
    'php_limits' => [
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time')
    ],
    'diagnostics' => $diag
], JSON_PRETTY_PRINT);
