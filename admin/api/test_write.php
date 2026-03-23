<?php
$file = __DIR__ . '/../../data/test.txt';
$content = "Test write at " . date('Y-m-d H:i:s');
$result = file_put_contents($file, $content);
if ($result !== false) {
    echo "Success: " . $result . " bytes written to " . realpath($file);
    echo "\nRead back: " . file_get_contents($file);
} else {
    echo "Failed to write to " . $file;
    echo "\nError: " . print_r(error_get_last(), true);
}
