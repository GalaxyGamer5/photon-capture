<?php
// admin/api/get-all-server-photos.php
header('Content-Type: application/json');

$baseDir = __DIR__ . '/../../';
$scanDirs = [
    'Portfolio' => 'assets/portfolio/',
    'Gallerien' => 'gallery/assets/'
];

$allPhotos = [];

function scanDirectory($dir, $base, $source, &$results) {
    if (!file_exists($dir)) return;
    
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $fullPath = $dir . $file;
        if (is_dir($fullPath)) {
            scanDirectory($fullPath . '/', $base, $source, $results);
        } else {
            if (preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $file)) {
                $results[] = [
                    'url' => str_replace($base, '../', $fullPath),
                    'name' => $file,
                    'source' => $source,
                    'path' => str_replace($base, '', $fullPath),
                    'date' => date("Y-m-d H:i:s", filemtime($fullPath)),
                    'size' => round(filesize($fullPath) / 1024 / 1024, 2) . ' MB'
                ];
            }
        }
    }
}

foreach ($scanDirs as $source => $relPath) {
    scanDirectory($baseDir . $relPath, $baseDir, $source, $allPhotos);
}

// Sort by date newest first
usort($allPhotos, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

echo json_encode([
    'success' => true, 
    'photos' => $allPhotos,
    'count' => count($allPhotos)
]);
