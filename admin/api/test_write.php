<?php
echo "DOCUMENT_ROOT: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "SCRIPT_FILENAME: " . $_SERVER['SCRIPT_FILENAME'] . "\n";
echo "__DIR__: " . __DIR__ . "\n";
$target = __DIR__ . '/../../data/pricing.json';
echo "Target pricing.json: " . $target . "\n";
echo "Real path of target: " . (realpath($target) ?: 'NOT FOUND') . "\n";
echo "Is writable: " . (is_writable($target) ? 'YES' : 'NO') . "\n";
echo "File exists: " . (file_exists($target) ? 'YES' : 'NO') . "\n";

$file = __DIR__ . '/../../data/test_live.txt';
if (file_put_contents($file, "Live test at " . date('Y-m-d H:i:s'))) {
    echo "SUCCESS: Wrote to test_live.txt\n";
    echo "Path: " . realpath($file) . "\n";
} else {
    echo "FAILURE: Could not write to test_live.txt\n";
}
