<?php
// admin/api/get-all-server-photos.php
header('Content-Type: application/json');

// Set a timeout to prevent hanging the browser
set_time_limit(10); 

$baseDir = realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR;
$allPhotos = [];
$debug = [];

$scanDirs = [
    'Portfolio' => 'assets/portfolio/',
    'Gallerien' => 'gallery/assets/'
];

foreach ($scanDirs as $source => $relPath) {
    $fullPath = $baseDir . $relPath;
    $debug[$source] = [
        'rel' => $relPath,
        'full' => $fullPath,
        'exists' => is_dir($fullPath)
    ];

    if (is_dir($fullPath)) {
        try {
            // Non-recursive glob search for subdirectories
            // This is much faster than recursive iterators if it hangs
            $subfolders = glob($fullPath . '*', GLOB_ONLYDIR);
            
            // Scan subfolders
            if ($subfolders) {
                foreach ($subfolders as $sub) {
                    $images = glob($sub . DIRECTORY_SEPARATOR . "*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}", GLOB_BRACE);
                    if ($images) {
                        foreach ($images as $img) {
                            $allPhotos[] = [
                                'url' => '../' . str_replace($baseDir, '', $img),
                                'name' => basename($img),
                                'source' => $source,
                                'date' => date("Y-m-d H:i:s", filemtime($img)),
                                'size' => round(filesize($img) / 1024 / 1024, 2) . ' MB'
                            ];
                        }
                    }
                }
            }
            
            // Scan root of the dir
            $rootImages = glob($fullPath . "*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}", GLOB_BRACE);
            if ($rootImages) {
                foreach ($rootImages as $img) {
                    $allPhotos[] = [
                        'url' => '../' . str_replace($baseDir, '', $img),
                        'name' => basename($img),
                        'source' => $source,
                        'date' => date("Y-m-d H:i:s", filemtime($img)),
                        'size' => round(filesize($img) / 1024 / 1024, 2) . ' MB'
                    ];
                }
            }
        } catch (Exception $e) {
            $debug[$source]['error'] = $e->getMessage();
        }
    }
}

// Sort by date newest first
usort($allPhotos, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

echo json_encode([
    'success' => true,
    'photos' => $allPhotos,
    'count' => count($allPhotos),
    'debug' => $debug
]);
