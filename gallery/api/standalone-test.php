<?php
// gallery/api/standalone-test.php

$requested_folder = 'demo';
$requested_image = '1.jpg';
$image_path = __DIR__ . '/../assets/' . $requested_folder . '/' . $requested_image;

if (!file_exists($image_path)) {
    die("Original image not found: $image_path\n");
}

$isProtected = true;

// Load GD
$image = @imagecreatefromjpeg($image_path);
if (!$image) {
    die("Failed to load image.\n");
}

if ($isProtected) {
    echo "Applying protection...\n";
    // 1. Blur
    for ($i = 0; $i < 6; $i++) {
        imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);
    }
    imagefilter($image, IMG_FILTER_PIXELATE, 2, true);

    // 2. Watermark
    $width = imagesx($image);
    $height = imagesy($image);
    $color_main = imagecolorallocatealpha($image, 255, 255, 255, 60);
    $color_black = imagecolorallocatealpha($image, 0, 0, 0, 80);
    $text = "PHOTON-CAPTURE";
    $fontSize = 5;
    $spacing_x = 250;
    $spacing_y = 150;
    
    for ($y = -200; $y < $height + 200; $y += $spacing_y) {
        $offset_x = ($y / $spacing_y) % 2 == 0 ? 0 : $spacing_x / 2;
        for ($x = -200 + $offset_x; $x < $width + 200; $x += $spacing_x) {
            imagestring($image, $fontSize, $x + 2, $y + 2, $text, $color_black);
            imagestring($image, $fontSize, $x, $y, $text, $color_main);
        }
    }

    // 3. Banner
    $bannerText = "PREVIEW - UNTIL PAID";
    $bannerWidth = imagefontwidth(5) * strlen($bannerText);
    imagefilledrectangle($image, 0, ($height/2) - 30, $width, ($height/2) + 30, $color_black);
    imagestring($image, 5, ($width - $bannerWidth) / 2, ($height / 2) - 8, $bannerText, $color_main);
}

$output_file = __DIR__ . '/standalone-output.jpg';
imagejpeg($image, $output_file, 75);
imagedestroy($image);

echo "Done! Output saved to: $output_file\n";
echo "Output file size: " . filesize($output_file) . " bytes\n";
?>
