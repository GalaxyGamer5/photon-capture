<?php
// Temporary diagnostic to reveal server paths
header('Content-Type: text/plain');
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "Script Filename: " . $_SERVER['SCRIPT_FILENAME'] . "\n";
echo "Script Name: " . $_SERVER['SCRIPT_NAME'] . "\n";
echo "__DIR__: " . __DIR__ . "\n";
echo "PHP Version: " . phpversion() . "\n";

$getImagePath = __DIR__ . '/gallery/api/get-image.php';
echo "\nget-image.php exists: " . (file_exists($getImagePath) ? "YES" : "NO") . "\n";

if (file_exists($getImagePath)) {
    $content = file_get_contents($getImagePath);
    echo "Has (int)(int) fix: " . (strpos($content, '(int)(int)') !== false ? "YES - FIXED" : "NO - OLD CODE") . "\n";
    echo "Has imagealphablending: " . (strpos($content, 'imagealphablending') !== false ? "YES" : "NO") . "\n";
}
?>
