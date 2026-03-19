<?php
/**
 * fix-gallery-images.php
 * One-time fix: converts/renames .png and other images in all gallery folders to .jpg
 * Run once via: https://photon-capture.de/admin/api/fix-gallery-images.php
 * DELETE this file after running.
 */
header('Content-Type: text/plain');

$galleryBase = __DIR__ . '/../../gallery/assets/';
$log = [];

if (!is_dir($galleryBase)) {
    die("Gallery directory not found: $galleryBase");
}

$folders = scandir($galleryBase);
foreach ($folders as $folder) {
    if ($folder === '.' || $folder === '..') continue;
    $folderPath = $galleryBase . $folder . '/';
    if (!is_dir($folderPath)) continue;

    $log[] = "--- Processing: $folder ---";

    // Collect all image files
    $files = scandir($folderPath);
    $imageFiles = [];
    foreach ($files as $file) {
        if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file)) {
            $imageFiles[] = $file;
        }
    }

    // Sort naturally so 1,2,3... order is preserved
    natsort($imageFiles);
    $imageFiles = array_values($imageFiles);

    // Convert each image to .jpg with sequential numbering
    $counter = 1;
    $tempFiles = [];

    foreach ($imageFiles as $file) {
        $src = $folderPath . $file;
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        // Skip files already correctly named (e.g., 1.jpg)
        if ($ext === 'jpg' || $ext === 'jpeg') {
            // Just rename to sequential number if needed
            $newName = $counter . '.jpg';
            $dest = $folderPath . $newName;
            if ($src !== $dest) {
                rename($src, $dest . '.tmp'); // temp rename to avoid conflicts
                $tempFiles[] = $dest . '.tmp';
            }
            $counter++;
            $log[] = "  Kept: $file → $newName";
            continue;
        }

        // Convert .png/.gif/.webp to .jpg
        $img = null;
        if ($ext === 'png') {
            $src_img = imagecreatefrompng($src);
            if ($src_img) {
                // Flatten transparency to white background
                $img = imagecreatetruecolor(imagesx($src_img), imagesy($src_img));
                $white = imagecolorallocate($img, 255, 255, 255);
                imagefill($img, 0, 0, $white);
                imagecopy($img, $src_img, 0, 0, 0, 0, imagesx($src_img), imagesy($src_img));
                imagedestroy($src_img);
            }
        } elseif ($ext === 'gif') {
            $img = imagecreatefromgif($src);
        } elseif ($ext === 'webp') {
            $img = imagecreatefromwebp($src);
        }

        $newName = $counter . '.jpg';
        $dest = $folderPath . $newName;

        if ($img) {
            if (imagejpeg($img, $dest . '.tmp', 92)) {
                imagedestroy($img);
                unlink($src); // Remove original
                $tempFiles[] = $dest . '.tmp';
                $log[] = "  Converted: $file → $newName";
            } else {
                $log[] = "  ERROR: Failed to convert $file";
            }
        } else {
            $log[] = "  ERROR: Could not open $file with GD";
        }

        $counter++;
    }

    // Finalize: rename all .tmp files to final names
    foreach ($tempFiles as $tmp) {
        $final = str_replace('.tmp', '', $tmp);
        rename($tmp, $final);
    }

    $log[] = "  Done: $counter images in $folder";
}

echo implode("\n", $log);
echo "\n\nDONE. Please delete this file from the server now:\n";
echo "admin/api/fix-gallery-images.php\n";
