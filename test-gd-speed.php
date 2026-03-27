<?php
$start = microtime(true);
$img = imagecreatetruecolor(6000, 4000); // 24 MP image
echo "Creation: " . (microtime(true) - $start) . "s\n";

$start = microtime(true);
for($i=0; $i<6; $i++) imagefilter($img, IMG_FILTER_GAUSSIAN_BLUR);
echo "6x Gaussian: " . (microtime(true) - $start) . "s\n";

$img2 = imagecreatetruecolor(6000, 4000);
$start = microtime(true);
imagefilter($img2, IMG_FILTER_PIXELATE, 8, true);
echo "1x Pixelate(8): " . (microtime(true) - $start) . "s\n";

$img3 = imagecreatetruecolor(6000, 4000);
$start = microtime(true);
imagefilter($img3, IMG_FILTER_SMOOTH, -10);
echo "Smooth: " . (microtime(true) - $start) . "s\n";
?>
