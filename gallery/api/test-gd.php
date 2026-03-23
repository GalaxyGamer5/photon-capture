<?php
header('Content-Type: text/plain');
echo "GD Enabled: " . (extension_loaded('gd') ? 'Yes' : 'No') . "\n";
echo "imagecreatefromjpeg exists: " . (function_exists('imagecreatefromjpeg') ? 'Yes' : 'No') . "\n";
echo "imagecreatefrompng exists: " . (function_exists('imagecreatefrompng') ? 'Yes' : 'No') . "\n";
echo "imagefilter exists: " . (function_exists('imagefilter') ? 'Yes' : 'No') . "\n";
echo "imagettftext exists: " . (function_exists('imagettftext') ? 'Yes' : 'No') . "\n";

$img = @imagecreatetruecolor(100, 100);
if ($img) {
    echo "imagecreatetruecolor works: Yes\n";
    $white = imagecolorallocate($img, 255, 255, 255);
    imagefilledrectangle($img, 0, 0, 99, 99, $white);
    $black = imagecolorallocate($img, 0, 0, 0);
    imagestring($img, 1, 5, 5, "Test", $black);
    
    ob_start();
    imagejpeg($img, null, 75);
    $data = ob_get_clean();
    echo "imagejpeg works: " . (strlen($data) > 0 ? 'Yes' : 'No') . " (Size: " . strlen($data) . ")\n";
    imagedestroy($img);
} else {
    echo "imagecreatetruecolor works: No\n";
}
?>
