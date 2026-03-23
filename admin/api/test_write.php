<?php
$dataDir = __DIR__ . '/../../data';
echo "Data Directory: " . realpath($dataDir) . "\n";
echo "Directory Writable: " . (is_writable($dataDir) ? 'YES' : 'NO') . "\n\n";

$files = scandir($dataDir);
echo str_pad("File", 20) . " | " . str_pad("Writable", 10) . " | " . "Permissions\n";
echo str_repeat("-", 45) . "\n";

foreach ($files as $file) {
    if ($file === '.' || $file === '..') continue;
    $path = $dataDir . '/' . $file;
    $writable = is_writable($path) ? 'YES' : 'NO';
    $perms = substr(sprintf('%o', fileperms($path)), -4);
    echo str_pad($file, 20) . " | " . str_pad($writable, 10) . " | " . $perms . "\n";
}
