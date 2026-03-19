<?php
// admin/api/get-all-server-photos.php
header('Content-Type: application/json');

$baseDir = realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR;
$allPhotos = [];

// 1. Portfolio (from JSON)
$portfolioFile = $baseDir . 'data/portfolio.json';
if (file_exists($portfolioFile)) {
    $data = json_decode(file_get_contents($portfolioFile), true);
    if (isset($data['images'])) {
        foreach ($data['images'] as $img) {
            $allPhotos[] = [
                'url' => '../assets/portfolio/' . $img['filename'],
                'name' => $img['filename'],
                'source' => 'Portfolio',
                'date' => $img['date'] ?? date("Y-m-d H:i:s", @filemtime($baseDir . 'assets/portfolio/' . $img['filename'])),
                'size' => @file_exists($baseDir . 'assets/portfolio/' . $img['filename']) ? round(filesize($baseDir . 'assets/portfolio/' . $img['filename']) / 1024 / 1024, 2) . ' MB' : '–'
            ];
        }
    }
}

// 2. Gallerien (Filesystem-First to avoid folder name mismatches)
$galleryPath = $baseDir . 'gallery/assets/';
if (is_dir($galleryPath)) {
    $folders = scandir($galleryPath);
    foreach ($folders as $folder) {
        if ($folder === '.' || $folder === '..' || !is_dir($galleryPath . $folder)) continue;
        
        $subPath = $galleryPath . $folder . DIRECTORY_SEPARATOR;
        $files = scandir($subPath);
        foreach ($files as $file) {
            if (preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $file)) {
                $url = '../gallery/assets/' . $folder . '/' . $file;
                $url = str_replace('\\', '/', $url); // Ensure forward slashes
                $allPhotos[] = [
                    'url' => $url,
                    'name' => $folder . ' - ' . $file,
                    'source' => 'Gallerien',
                    'date' => date("Y-m-d H:i:s", filemtime($subPath . $file)),
                    'size' => round(filesize($subPath . $file) / 1024 / 1024, 2) . ' MB'
                ];
            }
        }
    }
}

// Sort by date newest first
usort($allPhotos, function($a, $b) {
    if ($a['date'] === 'N/A') return 1;
    if ($b['date'] === 'N/A') return -1;
    return strtotime($b['date']) - strtotime($a['date']);
});

echo json_encode([
    'success' => true,
    'photos' => $allPhotos,
    'count' => count($allPhotos)
]);
