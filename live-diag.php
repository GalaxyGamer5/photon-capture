<?php
header('Content-Type: text/plain');
error_reporting(E_ALL);
ini_set('display_errors', 1);

function listDir($dir, $prefix = '') {
    if (!is_dir($dir)) {
        echo $prefix . "NOT A DIRECTORY: $dir\n";
        return;
    }
    echo $prefix . "DIRECTORY: $dir\n";
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            listDir($path, $prefix . '  ');
        } else {
            echo $prefix . "  FILE: $file (" . filesize($path) . " bytes)\n";
        }
    }
}

echo "Current Directory: " . __DIR__ . "\n";
echo "--- Gallery Data ---\n";
listDir(__DIR__ . '/gallery/data');
echo "\n--- Gallery Assets ---\n";
listDir(__DIR__ . '/gallery/assets');
?>
