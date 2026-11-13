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

// Extract useful EXIF metadata from an image file
function extract_exif_meta($filePath, $mimeType) {
    $meta = [
        'focal_length'  => null,
        'aperture'      => null,
        'iso'           => null,
        'shutter_speed' => null,
        'camera_model'  => null,
    ];
    if ($mimeType !== 'image/jpeg' && $mimeType !== 'image/jpg') return $meta;
    if (!function_exists('exif_read_data')) return $meta;

    $exif = @exif_read_data($filePath, 'EXIF,IFD0', true);
    if (!$exif) return $meta;

    // Camera model
    if (!empty($exif['IFD0']['Model'])) {
        $meta['camera_model'] = trim($exif['IFD0']['Model']);
    }

    // ISO
    if (!empty($exif['EXIF']['ISOSpeedRatings'])) {
        $iso = $exif['EXIF']['ISOSpeedRatings'];
        $meta['iso'] = 'ISO ' . (is_array($iso) ? $iso[0] : $iso);
    }

    // Focal length
    if (!empty($exif['EXIF']['FocalLength'])) {
        $fl = $exif['EXIF']['FocalLength'];
        if (strpos($fl, '/') !== false) {
            [$num, $den] = explode('/', $fl);
            $fl = $den != 0 ? round($num / $den) : $num;
        }
        $meta['focal_length'] = $fl . 'mm';
    }

    // Aperture (FNumber)
    if (!empty($exif['EXIF']['FNumber'])) {
        $fn = $exif['EXIF']['FNumber'];
        if (strpos($fn, '/') !== false) {
            [$num, $den] = explode('/', $fn);
            $fn = $den != 0 ? round($num / $den, 1) : $num;
        }
        $meta['aperture'] = 'f/' . $fn;
    }

    // Shutter speed (ExposureTime)
    if (!empty($exif['EXIF']['ExposureTime'])) {
        $et = $exif['EXIF']['ExposureTime'];
        if (strpos($et, '/') !== false) {
            [$num, $den] = explode('/', $et);
            if ($den != 0 && ($num / $den) < 1) {
                $meta['shutter_speed'] = '1/' . round($den / $num) . 's';
            } else {
                $meta['shutter_speed'] = round($num / $den, 2) . 's';
            }
        } else {
            $meta['shutter_speed'] = $et . 's';
        }
    }

    return $meta;
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

$targetDir = __DIR__ . '/../../assets/portfolio/';
if (!file_exists($targetDir)) {
    if (!@mkdir($targetDir, 0775, true)) {
        echo json_encode(['success' => false, 'error' => 'Failed to create portfolio directory. Check permissions of assets/ folder.']);
        exit;
    }
}
@chmod($targetDir, 0775);

require_once __DIR__ . '/../../includes/db.php';

$portfolioFile = __DIR__ . '/../../data/portfolio.json';
$portfolioData = ['images' => []];
if (file_exists($portfolioFile)) {
    $portfolioData = json_decode(file_get_contents($portfolioFile), true) ?: ['images' => []];
}

$db = null;
try {
    $db = getDB();
} catch (Exception $e) {}

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
    $now = date('Y-m-d H:i:s');

    // Extract EXIF before moving (tmp file is still readable)
    $exifMeta = extract_exif_meta($tmp_name, $mimeType);

    if (moveImage($tmp_name, $targetPath, $mimeType)) {
        @chmod($targetPath, 0666);
        $uploadedCount++;

        // Save to SQL database
        if ($db) {
            try {
                $ins = $db->prepare("INSERT INTO portfolio (id, filename, category, created_at, focal_length, aperture, iso, shutter_speed, camera_model) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $ins->execute([$id, $filename, $category, $now,
                    $exifMeta['focal_length'], $exifMeta['aperture'],
                    $exifMeta['iso'], $exifMeta['shutter_speed'], $exifMeta['camera_model']
                ]);
            } catch (Exception $e) {
                $errors[] = "DB error for {$name}: " . $e->getMessage();
            }
        }

        $portfolioData['images'][] = [
            'id' => $id,
            'filename' => $filename,
            'category' => $category,
            'date' => $now
        ];
    } else {
        $errors[] = "File {$name}: Failed to compress or save";
    }
}

if ($uploadedCount > 0) {
    file_put_contents($portfolioFile, json_encode($portfolioData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    @chmod($portfolioFile, 0777);
    echo json_encode([
        'success' => true,
        'uploaded' => $uploadedCount,
        'errors' => $errors
    ]);
} else {
    echo json_encode([
        'success' => false,
        'uploaded' => 0,
        'error' => !empty($errors) ? implode('; ', $errors) : 'Fehler beim Speichern der Bilder.',
        'errors' => $errors
    ]);
}
