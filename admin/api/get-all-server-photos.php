<?php
// admin/api/get-all-server-photos.php
header('Content-Type: application/json');

$baseDir = realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR;
$allPhotos = [];

// 1. Portfolio (from JSON index)
$portfolioFile = $baseDir . 'data/portfolio.json';
if (file_exists($portfolioFile)) {
    $data = json_decode(file_get_contents($portfolioFile), true);
    if (isset($data['images'])) {
        foreach ($data['images'] as $img) {
            $fullPath = $baseDir . 'assets/portfolio/' . $img['filename'];
            $allPhotos[] = [
                'url'    => '/assets/portfolio/' . $img['filename'],
                'path'   => 'assets/portfolio/' . $img['filename'],
                'name'   => $img['filename'],
                'source' => 'Portfolio',
                'date'   => $img['date'] ?? '',
                'size'   => file_exists($fullPath)
                    ? round(filesize($fullPath) / 1024 / 1024, 2) . ' MB'
                    : '–'
            ];
        }
    }
}

// 2. Gallerien — filesystem scan of gallery/assets/
$galleryPath = $baseDir . 'gallery' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR;
if (is_dir($galleryPath)) {
    $folders = scandir($galleryPath);
    foreach ($folders as $folder) {
        if ($folder === '.' || $folder === '..') continue;
        $subPath = $galleryPath . $folder . DIRECTORY_SEPARATOR;
        if (!is_dir($subPath)) continue;

        $files = scandir($subPath);
        foreach ($files as $file) {
            if (!preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $file)) continue;
            $fullPath = $subPath . $file;
            $relPath  = 'gallery/assets/' . $folder . '/' . $file;
            $allPhotos[] = [
                'url'    => '/' . $relPath,
                'path'   => $relPath,
                'name'   => $folder . ' — ' . $file,
                'source' => 'Gallerien',
                'date'   => date('Y-m-d H:i:s', filemtime($fullPath)),
                'size'   => round(filesize($fullPath) / 1024 / 1024, 2) . ' MB'
            ];
        }
    }
}

// Sort by date newest first
usort($allPhotos, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

echo json_encode([
    'success' => true,
    'photos'  => $allPhotos,
    'count'   => count($allPhotos)
]);
