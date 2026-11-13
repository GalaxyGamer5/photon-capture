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

$affectedGalleryFolders = [];
$affectedPortfolioFiles = [];

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

            // Also remove the cached thumbnail for this file (if it exists)
            $thumbPath = dirname($fullPath) . DIRECTORY_SEPARATOR . '_thumbs' . DIRECTORY_SEPARATOR . basename($fullPath);
            if (is_file($thumbPath)) {
                @unlink($thumbPath);
            }

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


require_once __DIR__ . '/../../includes/db.php';

$db = null;
try {
    $db = getDB();
} catch (Exception $e) {}

// Bulk update gallery users
if (!empty($affectedGalleryFolders)) {
    try {
        $folderCounts = [];
        foreach (array_keys($affectedGalleryFolders) as $folder) {
            $folderName = basename($folder);
            $imagesCount = 0;
            if (is_dir($folder)) {
                $filesList = [];
                foreach (scandir($folder) as $file) {
                    if (preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $file)) {
                        $filesList[] = $file;
                    }
                }
                natsort($filesList);
                $filesList = array_values($filesList);

                $counter = 1;
                foreach ($filesList as $file) {
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    $expectedName = $counter . '.' . $ext;
                    $oldPath = $folder . DIRECTORY_SEPARATOR . $file;
                    $newPath = $folder . DIRECTORY_SEPARATOR . $expectedName;

                    if ($oldPath !== $newPath && strtolower($file) !== $expectedName) {
                        rename($oldPath, $newPath);
                        $thumbsDir = $folder . DIRECTORY_SEPARATOR . '_thumbs';
                        $oldThumb  = $thumbsDir . DIRECTORY_SEPARATOR . $file;
                        $newThumb  = $thumbsDir . DIRECTORY_SEPARATOR . $expectedName;
                        if (is_file($oldThumb)) {
                            rename($oldThumb, $newThumb);
                        }
                    }
                    $imagesCount++;
                    $counter++;
                }
            }
            $folderCounts[$folderName] = $imagesCount;

            if ($db) {
                $stmt = $db->prepare("UPDATE users SET image_count = ? WHERE folder = ?");
                $stmt->execute([$imagesCount, $folderName]);
            }
        }

        if ($db) {
            syncUsersJs($db);
        } else {
            $usersFile = $baseDir . 'gallery' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'users.js';
            if (file_exists($usersFile)) {
                $content = file_get_contents($usersFile);
                if (preg_match('/window\.usersDatabase\s*=\s*({[\s\S]*?});/', $content, $matches)) {
                    $data = json_decode($matches[1], true);
                    if ($data !== null && isset($data['users']) && is_array($data['users'])) {
                        foreach ($data['users'] as &$user) {
                            if (isset($user['folder'], $folderCounts[$user['folder']])) {
                                $user['imageCount'] = $folderCounts[$user['folder']];
                            }
                        }
                        $jsContent = "// Client-side user database\n";
                        $jsContent .= "// In a real application, this would be a server-side database\n";
                        $jsContent .= "window.usersDatabase = " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . ";\n";
                        file_put_contents($usersFile, $jsContent);
                    }
                }
            }
        }
    } catch (\Throwable $e) {
        $errors[] = "User/gallery update error: " . $e->getMessage();
    }
}

// Bulk update portfolio
if (!empty($affectedPortfolioFiles)) {
    try {
        if ($db) {
            $placeholders = implode(',', array_fill(0, count($affectedPortfolioFiles), '?'));
            $stmt = $db->prepare("DELETE FROM portfolio WHERE filename IN ($placeholders)");
            $stmt->execute($affectedPortfolioFiles);
        }

        $portfolioFile = $baseDir . 'data' . DIRECTORY_SEPARATOR . 'portfolio.json';
        if (file_exists($portfolioFile)) {
            $data = json_decode(file_get_contents($portfolioFile), true);
            if ($data !== null && isset($data['images']) && is_array($data['images'])) {
                $data['images'] = array_values(array_filter($data['images'], function($img) use ($affectedPortfolioFiles) {
                    return isset($img['filename']) && !in_array($img['filename'], $affectedPortfolioFiles);
                }));
                file_put_contents($portfolioFile, json_encode($data, JSON_PRETTY_PRINT));
            }
        }
    } catch (\Throwable $e) {
        $errors[] = "Portfolio update error: " . $e->getMessage();
    }
}

echo json_encode(['success' => true, 'deleted' => $deleted, 'errors' => $errors]);
