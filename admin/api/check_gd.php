<?php
// admin/api/check_gd.php
header('Content-Type: text/plain');

echo "PHP Version: " . phpversion() . "\n";
echo "GD Support: " . (extension_loaded('gd') ? 'Yes' : 'No') . "\n";

if (extension_loaded('gd')) {
    $info = gd_info();
    echo "GD Version: " . $info['GD Version'] . "\n";
    echo "JPEG Support: " . ($info['JPEG Support'] ? 'Yes' : 'No') . "\n";
    echo "PNG Support: " . ($info['PNG Support'] ? 'Yes' : 'No') . "\n";
    echo "WebP Support: " . ($info['WebP Support'] ? 'Yes' : 'No') . "\n";
    echo "FreeType Support: " . ($info['FreeType Support'] ? 'Yes' : 'No') . "\n";
}

echo "ImageMagick (Imagick) Support: " . (extension_loaded('imagick') ? 'Yes' : 'No') . "\n";
echo "Exec function available: " . (function_exists('exec') ? 'Yes' : 'No') . "\n";

if (function_exists('exec')) {
    $output = [];
    exec('convert -version', $output, $return);
    echo "ImageMagick CLI version: " . ($return === 0 ? implode("\n", $output) : 'Not found') . "\n";
}
?>
