<?php
// test_gd.php
header('Content-Type: text/plain');
if (extension_loaded('gd')) {
    echo "GD is LOADED\n";
    $img = imagecreatetruecolor(100, 100);
    if ($img) {
        echo "Image created successfully\n";
        imagedestroy($img);
    } else {
        echo "Failed to create image\n";
    }
} else {
    echo "GD is NOT LOADED\n";
}
