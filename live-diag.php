<?php
// Script to force apply the watermark fix to get-image.php
header('Content-Type: text/plain');

$getImagePath = __DIR__ . '/gallery/api/get-image.php';
$targetPath1 = __DIR__ . '/api/get-image.php';

// Find which one exists
$actualPath = false;
if (file_exists($getImagePath)) {
    $actualPath = $getImagePath;
} elseif (file_exists($targetPath1)) {
    $actualPath = $targetPath1;
}

if (!$actualPath) {
    die("Error: Could not find get-image.php on this server.");
}

echo "Found get-image.php at: " . $actualPath . "\n";

$content = file_get_contents($actualPath);

// Check if it's already fixed
if (strpos($content, '(int)(int)') !== false) {
    echo "Status: ALREADY FIXED. The watermark should be working.\n";
    exit;
}

// Apply the missing fix
// The old code has: $offset_x = (int)(($y / $spacing_y) % 2 == 0 ? 0 : $spacing_x / 2);
$oldLine = '$offset_x = (int)(($y / $spacing_y) % 2 == 0 ? 0 : $spacing_x / 2);';
$newLine = '$offset_x = (int)((int)($y / $spacing_y) % 2 == 0 ? 0 : $spacing_x / 2);';

if (strpos($content, $oldLine) !== false) {
    $content = str_replace($oldLine, $newLine, $content);
    file_put_contents($actualPath, $content);
    echo "Status: SUCCESSFULLY APPLIED FIX! The watermark is now repaired.\n";
} else {
    echo "Status: Could not find the specific line to fix. It may be a different version.\n";
}
?>
