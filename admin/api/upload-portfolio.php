<?php
session_start();
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
    if ($mimeType === 'image/jpeg' || $mimeType === 'image/jpg') {
        $oldMemoryLimit = ini_get('memory_limit');
        @ini_set('memory_limit', '1024M'); // 100MP files need ~800MB RAM to process
        
        $orientation = 1;
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($sourcePath);
            if ($exif !== false && isset($exif['Orientation'])) {
                $orientation = $exif['Orientation'];
            }
        } else {
            $orientation = fallback_get_jpeg_orientation($sourcePath);
        }
        
        if (!empty($orientation) && $orientation != 1) {
            $image = @imagecreatefromjpeg($sourcePath);
            if ($image) {
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
                imagedestroy($image);
                @ini_set('memory_limit', $oldMemoryLimit);
                if ($success) return true;
            }
        }
        @ini_set('memory_limit', $oldMemoryLimit);
    }
    return move_uploaded_file($sourcePath, $destinationPath);
}

if (!isset($_POST['category']) || !isset($_FILES['photos'])) {
    echo json_encode(['success' => false, 'error' => 'Missing category or photos']);
    exit;
}

$category = $_POST['category'];
$allowedCategories = ['portrait', 'event', 'pet'];
if (!in_array($category, $allowedCategories)) {
    echo json_encode(['success' => false, 'error' => 'Invalid category']);
    exit;
}

$targetDir = '../../assets/portfolio/';
if (!file_exists($targetDir)) {
    if (!@mkdir($targetDir, 0775, true)) {
        echo json_encode(['success' => false, 'error' => 'Failed to create portfolio directory. Check permissions of assets/ folder.']);
        exit;
    }
}

$portfolioFile = __DIR__ . '/../../data/portfolio.json';
$portfolioData = ['images' => []];
if (file_exists($portfolioFile)) {
    $portfolioData = json_decode(file_get_contents($portfolioFile), true);
}

$uploadedCount = 0;
$errors = [];
$files = $_FILES['photos'];
$fileCount = is_array($files['name']) ? count($files['name']) : 1;

for ($i = 0; $i < $fileCount; $i++) {
    $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];
    $tmp_name = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
    $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];

    if ($error !== UPLOAD_ERR_OK) {
        $errors[] = "File {$name}: Upload error";
        continue;
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $tmp_name);
    finfo_close($finfo);
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
    if (!in_array($mimeType, $allowedTypes)) {
        $errors[] = "File {$name}: Invalid type (only JPG/PNG/WEBP allowed)";
        continue;
    }
    
    $ext = 'jpg';
    if ($mimeType === 'image/png') $ext = 'png';
    if ($mimeType === 'image/webp') $ext = 'webp';
    
    $id = uniqid();
    $filename = $id . '_' . time() . '.' . $ext;
    $targetPath = $targetDir . $filename;
    
    if (moveImage($tmp_name, $targetPath, $mimeType)) {
        $uploadedCount++;
        $portfolioData['images'][] = [
            'id' => $id,
            'filename' => $filename,
            'category' => $category,
            'date' => date('Y-m-d H:i:s')
        ];
    } else {
        $errors[] = "File {$name}: Failed to compress or save";
    }
}

if ($uploadedCount > 0) {
    file_put_contents($portfolioFile, json_encode($portfolioData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

echo json_encode([
    'success' => true,
    'uploaded' => $uploadedCount,
    'errors' => $errors
]);
