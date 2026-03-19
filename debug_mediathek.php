<?php
// debug_mediathek.php
$baseDir = realpath(__DIR__ . '/') . '/';
echo "Base Dir: $baseDir\n";

$scanDirs = [
    'Portfolio' => 'assets/portfolio/',
    'Gallerien' => 'gallery/assets/'
];

function scanDirectory($dir, $base, $source, &$results) {
    if (!file_exists($dir)) {
        echo "Dir not found: $dir\n";
        return;
    }
    
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
                    'full' => $fullPath
                ];
            }
        }
    }
}

$allPhotos = [];
foreach ($scanDirs as $source => $relPath) {
    scanDirectory($baseDir . $relPath, $baseDir, $source, $allPhotos);
}

echo "Found " . count($allPhotos) . " photos.\n";
foreach ($allPhotos as $p) {
    echo "URL: " . $p['url'] . " (Full: " . $p['full'] . ")\n";
}
