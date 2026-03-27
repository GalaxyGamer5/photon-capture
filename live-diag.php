<?php
// Strict diagnostic to check the actual active files!
$path1 = '/usr/share/nginx/html/photon-capture/gallery/api/get-image.php';
$path2 = '/usr/share/nginx/html/photon-capture/api/get-image.php';

echo "Path1 (gallery/api) exists: " . (file_exists($path1) ? "YES" : "NO") . "\n";
if (file_exists($path1)) echo "Path1 fix: " . (strpos(file_get_contents($path1), '(int)(int)') !== false ? "FIXED" : "OLD") . "\n";

echo "Path2 (api/) exists: " . (file_exists($path2) ? "YES" : "NO") . "\n";
if (file_exists($path2)) echo "Path2 fix: " . (strpos(file_get_contents($path2), '(int)(int)') !== false ? "FIXED" : "OLD") . "\n";
?>
