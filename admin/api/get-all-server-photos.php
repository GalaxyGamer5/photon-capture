<?php
// admin/api/get-all-server-photos.php
header('Content-Type: application/json');

$baseDir = realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR;
$allPhotos = [];

// 1. Load Portfolio from JSON
$portfolioFile = $baseDir . 'data/portfolio.json';
if (file_exists($portfolioFile)) {
    $data = json_decode(file_get_contents($portfolioFile), true);
    if (isset($data['images'])) {
        foreach ($data['images'] as $img) {
            $allPhotos[] = [
                'url' => '../assets/portfolio/' . $img['filename'],
                'name' => $img['filename'],
                'source' => 'Portfolio',
                'date' => $img['date'] ?? date("Y-m-d H:i:s", filemtime($baseDir . 'assets/portfolio/' . $img['filename'])),
                'size' => file_exists($baseDir . 'assets/portfolio/' . $img['filename']) ? round(filesize($baseDir . 'assets/portfolio/' . $img['filename']) / 1024 / 1024, 2) . ' MB' : 'N/A'
            ];
        }
    }
}

// 2. Load Gallerien from users.js
$usersFile = $baseDir . 'gallery/data/users.js';
if (file_exists($usersFile)) {
    $content = file_get_contents($usersFile);
    if (preg_match('/window\.usersDatabase\s*=\s*({[\s\S]*?});/', $content, $matches)) {
        $usersData = json_decode($matches[1], true);
        if (isset($usersData['users'])) {
            foreach ($usersData['users'] as $user) {
                $folder = $user['folder'];
                $count = $user['imageCount'] ?? 0;
                for ($i = 1; $i <= $count; $i++) {
                    $filename = $i . '.jpg';
                    $relPath = 'gallery/assets/' . $folder . '/' . $filename;
                    $fullPath = $baseDir . $relPath;
                    
                    // Note: We don't use file_exists() here to avoid hanging if the filesystem is slow.
                    // We assume that if it's in the database, it exists.
                    $allPhotos[] = [
                        'url' => '../' . $relPath,
                        'name' => $user['name'] . ' - #' . $i,
                        'source' => 'Gallerien',
                        'date' => @date("Y-m-d H:i:s", @filemtime($fullPath)) ?: 'N/A',
                        'size' => '–'
                    ];
                }
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
