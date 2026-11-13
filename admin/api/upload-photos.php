<?php
// Start session for admin auth
session_start();

// Check admin authentication


// Set JSON header
header('Content-Type: application/json');

// Fallback function to read JPEG EXIF Orientation without the PHP exif extension
if (!function_exists('fallback_get_jpeg_orientation')) {
    function fallback_get_jpeg_orientation($filename) {
        $fp = @fopen($filename, 'rb');
        if (!$fp) return 1;
        $data = fread($fp, 2);
        if ($data !== "\xFF\xD8") { fclose($fp); return 1; }
        while (!feof($fp)) {
            $marker = fread($fp, 2);
            if (strlen($marker) < 2) break;
            $marker_byte = ord($marker[1]);
            if ($marker_byte === 0xE1) {
                $len_data = fread($fp, 2);
                if (strlen($len_data) < 2) break;
                $len = unpack('n', $len_data)[1];
                $exif_data = fread($fp, $len - 2);
                if (substr($exif_data, 0, 6) === "Exif\0\0") {
                    $tiff_header = substr($exif_data, 6);
                    $endian = substr($tiff_header, 0, 2);
                    $is_big = ($endian === 'MM');
                    if (strlen($tiff_header) >= 8) {
                        $offset = unpack($is_big ? 'N' : 'V', substr($tiff_header, 4, 4))[1];
                        if (strlen($tiff_header) >= $offset + 2) {
                            $num_tags = unpack($is_big ? 'n' : 'v', substr($tiff_header, $offset, 2))[1];
                            $offset += 2;
                            for ($i = 0; $i < $num_tags; $i++) {
                                if (strlen($tiff_header) >= $offset + $i * 12 + 10) {
                                    $tag = unpack($is_big ? 'n' : 'v', substr($tiff_header, $offset + $i * 12, 2))[1];
                                    if ($tag === 0x0112) {
                                        $orientation = unpack($is_big ? 'n' : 'v', substr($tiff_header, $offset + $i * 12 + 8, 2))[1];
                                        fclose($fp);
                                        return $orientation;
                                    }
                                }
                            }
                        }
                    }
                }
                break;
            } else {
                $len_data = fread($fp, 2);
                if (strlen($len_data) < 2) break;
                $len = unpack('n', $len_data)[1];
                fseek($fp, $len - 2, SEEK_CUR);
            }
        }
        fclose($fp);
        return 1;
    }
}

// Move image and fix EXIF orientation for JPEGs
function moveImage($sourcePath, $destinationPath, $mimeType) {
    $log = __DIR__ . '/upload-debug.log';
    file_put_contents($log, "[" . date('H:i:s') . "] Uploading $mimeType\n", FILE_APPEND);
    
    if ($mimeType === 'image/jpeg' || $mimeType === 'image/jpg') {
        $oldMemoryLimit = ini_get('memory_limit');
        $setMem = @ini_set('memory_limit', '1024M'); 
        file_put_contents($log, "  Mem old: $oldMemoryLimit. Set 1024M: " . ($setMem !== false ? "ok" : "fail") . "\n", FILE_APPEND);
        
        $orientation = 1;
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($sourcePath);
            if ($exif !== false && isset($exif['Orientation'])) {
                $orientation = $exif['Orientation'];
            }
        } else {
            $orientation = fallback_get_jpeg_orientation($sourcePath);
            file_put_contents($log, "  Using fallback pure-PHP reader\n", FILE_APPEND);
        }
        
        file_put_contents($log, "  Orientation: $orientation\n", FILE_APPEND);
        
        if (!empty($orientation) && $orientation != 1) {
            file_put_contents($log, "  Attempting imagecreatefromjpeg...\n", FILE_APPEND);
            $image = @imagecreatefromjpeg($sourcePath);
            if ($image) {
                file_put_contents($log, "  imagecreatefromjpeg SUCCEEDED\n", FILE_APPEND);
                switch ($orientation) {
                    case 3:
                        $image = imagerotate($image, 180, 0);
                        break;
                    case 6:
                        $image = imagerotate($image, -90, 0);
                        break;
                    case 8:
                        $image = imagerotate($image, 90, 0);
                        break;
                }
                $success = imagejpeg($image, $destinationPath, 95);
                file_put_contents($log, "  imagejpeg save: " . ($success ? "SUCCESS" : "FAIL") . "\n", FILE_APPEND);
                imagedestroy($image);
                @ini_set('memory_limit', $oldMemoryLimit);
                if ($success) return true;
            } else {
                file_put_contents($log, "  imagecreatefromjpeg FAILED (likely out of memory)\n", FILE_APPEND);
            }
        }
        @ini_set('memory_limit', $oldMemoryLimit);
    }
    file_put_contents($log, "  Falling back to move_uploaded_file\n", FILE_APPEND);
    return move_uploaded_file($sourcePath, $destinationPath);
}

// Get gallery ID from POST
if (!isset($_POST['galleryId']) || !isset($_FILES['photos'])) {
    echo json_encode(['success' => false, 'error' => 'Missing gallery ID or photos']);
    exit;
}

require_once __DIR__ . '/../../includes/db.php';

$galleryId = $_POST['galleryId'] ?? '';
$folderParam = trim($_POST['folder'] ?? '');

$db = null;
$gallery = null;
try {
    $db = getDB();

    // 1. Match by explicit folder if passed
    if ($folderParam !== '') {
        $stmt = $db->prepare("SELECT id, user_id, title, folder, image_count FROM galleries WHERE folder = ? LIMIT 1");
        $stmt->execute([$folderParam]);
        $gRow = $stmt->fetch();
        if ($gRow) {
            $uStmt = $db->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
            $uStmt->execute([$gRow['user_id']]);
            $uName = $uStmt->fetchColumn();
            $gallery = [
                'id' => (string)$gRow['user_id'],
                'username' => $uName ?: '',
                'folder' => $gRow['folder'],
                'imageCount' => (int)$gRow['image_count']
            ];
        }
    }

    // 2. Match by galleries table
    if (!$gallery && $galleryId !== '') {
        $stmt = $db->prepare("SELECT id, user_id, title, folder, image_count FROM galleries WHERE id = ? OR folder = ? LIMIT 1");
        $stmt->execute([$galleryId, $galleryId]);
        $gRow = $stmt->fetch();
        if ($gRow) {
            $uStmt = $db->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
            $uStmt->execute([$gRow['user_id']]);
            $uName = $uStmt->fetchColumn();
            $gallery = [
                'id' => (string)$gRow['user_id'],
                'username' => $uName ?: '',
                'folder' => $gRow['folder'],
                'imageCount' => (int)$gRow['image_count']
            ];
        }
    }

    // 3. Match by users table
    if (!$gallery && $galleryId !== '') {
        $stmt = $db->prepare("SELECT id, username, folder, image_count FROM users WHERE id = ? OR username = ? LIMIT 1");
        $stmt->execute([$galleryId, $galleryId]);
        $uRow = $stmt->fetch();
        if ($uRow) {
            $gallery = [
                'id' => (string)$uRow['id'],
                'username' => $uRow['username'],
                'folder' => $uRow['folder'],
                'imageCount' => (int)$uRow['image_count']
            ];
        }
    }
} catch (Exception $e) {}

// Fallback to users.js if not found in SQL
$usersFile = __DIR__ . '/../../gallery/data/users.js';
$usersData = ['users' => []];
if (!$gallery && file_exists($usersFile)) {
    $usersContent = file_get_contents($usersFile);
    if ($usersContent && preg_match('/window\.usersDatabase\s*=\s*({[\s\S]*?});/', $usersContent, $matches)) {
        $usersData = json_decode($matches[1], true) ?: ['users' => []];
        foreach ($usersData['users'] as &$user) {
            if ($user['id'] === $galleryId || ($user['username'] ?? '') === $galleryId || ($folderParam !== '' && ($user['folder'] ?? '') === $folderParam)) {
                $gallery = &$user;
                break;
            }
        }
    }
}

if (!$gallery) {
    echo json_encode(['success' => false, 'error' => 'Gallery/User not found']);
    exit;
}

// Target directory
$targetDir = __DIR__ . '/../../gallery/assets/' . $gallery['folder'] . '/';
if (!file_exists($targetDir)) {
    if (!@mkdir($targetDir, 0775, true)) {
        echo json_encode(['success' => false, 'error' => 'Failed to create gallery directory. Check permissions.']);
        exit;
    }
}

// Get current image count from actual files on disk
$existingFiles = 0;
if (is_dir($targetDir)) {
    foreach (scandir($targetDir) as $f) {
        if (preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $f)) $existingFiles++;
    }
}
$currentCount = max($existingFiles, (int)($gallery['imageCount'] ?? 0));
$uploadedCount = 0;
$errors = [];

// Handle multiple file uploads
$files = $_FILES['photos'];
$fileCount = is_array($files['name']) ? count($files['name']) : 1;

for ($i = 0; $i < $fileCount; $i++) {
    $err = is_array($files['error']) ? $files['error'][$i] : $files['error'];
    $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
    $origName = is_array($files['name']) ? $files['name'][$i] : $files['name'];

    if ($err !== UPLOAD_ERR_OK) {
        $errors[] = "File {$origName}: Upload error";
        continue;
    }
    
    // Validate file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $tmpName);
    finfo_close($finfo);
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
    if (!in_array($mimeType, $allowedTypes)) {
        $errors[] = "File {$origName}: Invalid type (only JPG/PNG/WEBP allowed)";
        continue;
    }
    
    // Determine file extension
    $ext = ($mimeType === 'image/png') ? 'png' : (($mimeType === 'image/webp') ? 'webp' : 'jpg');
    
    // New filename (auto-increment)
    $currentCount++;
    $newFilename = $currentCount . '.' . $ext;
    $targetPath = $targetDir . $newFilename;
    
    // Move uploaded file
    if (moveImage($tmpName, $targetPath, $mimeType)) {
        $uploadedCount++;
    } else {
        $errors[] = "File {$origName}: Failed to compress or save";
        $currentCount--; // Rollback counter
    }
}

// Update image counts in SQL database
if ($db) {
    try {
        $updGal = $db->prepare("UPDATE galleries SET image_count = ? WHERE folder = ?");
        $updGal->execute([$currentCount, $gallery['folder']]);

        // Also update primary user record if matching
        if (!empty($gallery['id'])) {
            $updUsr = $db->prepare("UPDATE users SET image_count = (SELECT COALESCE(SUM(image_count), 0) FROM galleries WHERE user_id = ?) WHERE id = ?");
            $updUsr->execute([$gallery['id'], $gallery['id']]);
        }
        syncUsersJs($db);
    } catch (Exception $e) {}
}

$gallery['imageCount'] = $currentCount;
if (!empty($usersData['users'])) {
    $jsContent = "// Client-side user database\n";
    $jsContent .= "// In a real application, this would be a server-side database\n";
    $jsContent .= "window.usersDatabase = " . json_encode($usersData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . ";\n";
    @file_put_contents($usersFile, $jsContent);
}

echo json_encode([
    'success' => true,
    'uploaded' => $uploadedCount,
    'newTotal' => $currentCount,
    'errors' => $errors
]);
