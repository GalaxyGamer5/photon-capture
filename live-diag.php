<?php
// Temporary diagnostic - check PHP version, GD, and get-image.php content
header('Content-Type: text/plain');

echo "PHP Version: " . phpversion() . "\n";
echo "GD Version: " . (function_exists('gd_info') ? json_encode(gd_info()) : 'NOT INSTALLED') . "\n\n";

// Check if our newer version of get-image.php is deployed
$getImagePath = __DIR__ . '/../gallery/api/get-image.php';
if (file_exists($getImagePath)) {
    $content = file_get_contents($getImagePath);
    // Check for our fix marker
    echo "get-image.php contains (int)(int) cast: " . (strpos($content, '(int)(int)') !== false ? "YES - FIX DEPLOYED" : "NO - OLD VERSION") . "\n";
    echo "get-image.php contains imagealphablending: " . (strpos($content, 'imagealphablending') !== false ? "YES" : "NO") . "\n";
    echo "get-image.php contains error_log debugging: " . (strpos($content, 'Watermark debug') !== false ? "YES (debug logs still present)" : "NO (clean)") . "\n";
    echo "get-image.php size: " . strlen($content) . " bytes\n";
} else {
    echo "get-image.php not found at $getImagePath\n";
}
?>
