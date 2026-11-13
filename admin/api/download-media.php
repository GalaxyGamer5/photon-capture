<?php
// admin/api/download-media.php
if (!isset($_GET['path'])) {
    http_response_code(400);
    exit('No path provided');
}

$relPath = (string)$_GET['path'];

// Security: strip directory traversal attempts
$relPath = ltrim(str_replace(['../', '..\\', '..'], '', $relPath), '/\\');

// Only allow within gallery/assets or assets/portfolio
$isAllowedPrefix = (
    strpos($relPath, 'gallery/assets/') === 0 ||
    strpos($relPath, 'gallery\\assets\\') === 0 ||
    strpos($relPath, 'assets/portfolio/') === 0 ||
    strpos($relPath, 'assets\\portfolio\\') === 0
);

if (!$isAllowedPrefix) {
    http_response_code(403);
    exit('Forbidden');
}

$baseDir = realpath(__DIR__ . '/../../');
if (!$baseDir) {
    $baseDir = dirname(dirname(__DIR__));
}
$baseDir = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR;

$fullPath = $baseDir . $relPath;

// Try realpath first
$realFullPath = realpath($fullPath);
if ($realFullPath && is_file($realFullPath)) {
    $fullPath = $realFullPath;
} elseif (!is_file($fullPath)) {
    http_response_code(404);
    exit('File not found');
}

// Determine MIME type
$mime = 'image/jpeg';
$ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
if ($ext === 'png') {
    $mime = 'image/png';
} elseif ($ext === 'webp') {
    $mime = 'image/webp';
} elseif ($ext === 'gif') {
    $mime = 'image/gif';
} elseif (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detected = finfo_file($finfo, $fullPath);
    if ($detected) $mime = $detected;
    if (PHP_VERSION_ID < 80500) {
        @finfo_close($finfo);
    }
}

// Output the file directly (this avoids CORS and path issues on subdomains)
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($fullPath));
header('Cache-Control: public, max-age=86400');
readfile($fullPath);
