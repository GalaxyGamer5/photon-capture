<?php
// admin/api/delete-media.php
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['paths']) || !is_array($input['paths'])) {
    echo json_encode(['success' => false, 'error' => 'No paths provided']);
    exit;
}

$baseDir = realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR;
$deleted = 0;
$errors = [];

foreach ($input['paths'] as $relPath) {
    // Security: strip any directory traversal attempts and ensure path stays within base
    $relPath = ltrim(str_replace('..', '', $relPath), '/\\');
    $fullPath = realpath($baseDir . $relPath);

    // Only allow deletion within gallery/assets or assets/portfolio
    if (!$fullPath || strpos($fullPath, $baseDir) !== 0) {
        $errors[] = "Blocked: $relPath (outside allowed directories)";
        continue;
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

    if (!$inAllowed) {
        $errors[] = "Blocked: $relPath (not in allowed directories)";
        continue;
    }

    if (is_file($fullPath)) {
        if (unlink($fullPath)) {
            $deleted++;

            // Track for bulk update
            if (strpos($fullPath, 'gallery' . DIRECTORY_SEPARATOR . 'assets') !== false) {
                $affectedGalleryFolders[dirname($fullPath)] = true;
            }
            if (strpos($fullPath, 'assets' . DIRECTORY_SEPARATOR . 'portfolio') !== false) {
                $affectedPortfolioFiles[] = basename($fullPath);
            }
        } else {
            $errors[] = "Failed to delete: $relPath";
        }
    } else {
        $errors[] = "Not a file: $relPath";
    }
}

// Bulk update gallery users.js
if (!empty($affectedGalleryFolders)) {
    $usersFile = $baseDir . 'gallery' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'users.js';
    if (file_exists($usersFile)) {
        $content = file_get_contents($usersFile);
        if (preg_match('/window\.usersDatabase\s*=\s*({[\s\S]*?});/', $content, $matches)) {
            $data = json_decode($matches[1], true);
            $changed = false;
            
            foreach (array_keys($affectedGalleryFolders) as $folder) {
                $folderName = basename($folder);
                foreach ($data['users'] as &$user) {
                    if ($user['folder'] === $folderName) {
                        $images = glob($folder . DIRECTORY_SEPARATOR . '*.{jpg,jpeg,png,webp}', GLOB_BRACE);
                        $user['imageCount'] = $images ? count($images) : 0;
                        $changed = true;
                        break;
                    }
                }
            }
            if ($changed) {
                $jsContent = "// Client-side user database\n";
                $jsContent .= "// In a real application, this would be a server-side database\n";
                $jsContent .= "window.usersDatabase = " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . ";\n";
                file_put_contents($usersFile, $jsContent);
            }
        }
    }
}

// Bulk update portfolio.json
if (!empty($affectedPortfolioFiles)) {
    $portfolioFile = $baseDir . 'data' . DIRECTORY_SEPARATOR . 'portfolio.json';
    if (file_exists($portfolioFile)) {
        $data = json_decode(file_get_contents($portfolioFile), true);
        if (isset($data['images'])) {
            $data['images'] = array_values(array_filter($data['images'], function($img) use ($affectedPortfolioFiles) {
                return !in_array($img['filename'], $affectedPortfolioFiles);
            }));
            file_put_contents($portfolioFile, json_encode($data, JSON_PRETTY_PRINT));
        }
    }
}

echo json_encode(['success' => true, 'deleted' => $deleted, 'errors' => $errors]);
