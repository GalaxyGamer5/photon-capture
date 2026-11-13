<?php
// admin/api/download-media.php
if (!isset($_GET['path'])) {
    http_response_code(400);
    exit('No path provided');
}

$baseDir = realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR;
$relPath = (string)$_GET['path'];

// Security: strip directory traversal attempts
$relPath = ltrim(str_replace('..', '', $relPath), '/\\');
$fullPath = realpath($baseDir . $relPath);

// Only allow within gallery/assets or assets/portfolio
if (!$fullPath || strpos($fullPath, $baseDir) !== 0) {
    http_response_code(403);
    exit('Forbidden');
}

$allowed = [
    $baseDir . 'gallery' . DIRECTORY_SEPARATOR . 'assets',
    $baseDir . 'assets' . DIRECTORY_SEPARATOR . 'portfolio'
];

$inAllowed = false;
foreach ($allowed as $a) {
    $realA = realpath($a);
    if ($realA && strpos($fullPath, $realA) === 0) {
        $inAllowed = true;
        break;
    }
}

if (!$inAllowed || !is_file($fullPath)) {
    http_response_code(404);
    exit('File not found');
}

// Determine MIME type
$mime = 'image/jpeg';
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $fullPath);
    if (PHP_VERSION_ID < 80500) {
        @finfo_close($finfo);
    }
}

// Output the file directly (this avoids CORS and path issues on subdomains)
header('Content-Type: ' . ($mime ?: 'image/jpeg'));
header('Content-Length: ' . filesize($fullPath));
header('Cache-Control: public, max-age=86400');
readfile($fullPath);
