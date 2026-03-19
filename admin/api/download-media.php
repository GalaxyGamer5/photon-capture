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
    if (strpos($fullPath, realpath($a)) === 0) {
        $inAllowed = true;
        break;
    }
}

if (!$inAllowed || !is_file($fullPath)) {
    http_response_code(404);
    exit('File not found');
}

// Determine MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $fullPath);
finfo_close($finfo);

// Output the file directly (this avoids CORS since it's same-origin on the admin subdomain)
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($fullPath));
readfile($fullPath);
