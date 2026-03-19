<?php
// admin/api/deep-search-photos.php
header('Content-Type: application/json');
set_time_limit(30);

$baseDir = realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR;
$allFound = [];

// Recursive scanning of EVERYTHING (except .git and admin/)
function deepScan($dir, $base, &$results) {
    if (!is_dir($dir)) return;
    
    $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::SELF_FIRST);
    
    foreach ($files as $file) {
        if ($file->isDir()) {
            if (strpos($file->getFilename(), '.') === 0) continue; // Skip hidden dirs
            if ($file->getFilename() === 'admin') continue;
            if ($file->getFilename() === 'node_modules') continue;
        } else {
            if (preg_match('/\.(jpg|jpeg|png|webp)$/i', $file->getFilename())) {
                // Skip icons/favicons
                if ($file->getSize() < 5000) continue; 
                
                $results[] = [
                    'url' => '../' . str_replace($base, '', $file->getPathname()),
                    'name' => $file->getFilename(),
                    'path' => str_replace($base, '', $file->getPathname()),
                    'size' => round($file->getSize() / 1024 / 1024, 2) . ' MB'
                ];
            }
        }
    }
}

try {
    deepScan($baseDir, $baseDir, $allFound);
} catch (Exception $e) {
    $error = $e->getMessage();
}

echo json_encode([
    'success' => true,
    'count' => count($allFound),
    'photos' => $allFound,
    'error' => $error ?? null
]);
