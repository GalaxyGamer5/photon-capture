<?php
// check_img_api.php
header('Content-Type: text/plain');
$testUrl = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/gallery/assets/demo/1.jpg';
echo "Testing URL: $testUrl\n";

$headers = @get_headers($testUrl);
if ($headers) {
    echo "Status: " . $headers[0] . "\n";
} else {
    echo "Could not fetch headers. Maybe the host is not accessible from PHP.\n";
}

$localPath = realpath(__DIR__ . '/../../gallery/assets/demo/1.jpg');
echo "Local Path: $localPath\n";
echo "Exists: " . (file_exists($localPath) ? 'Yes' : 'No') . "\n";
echo "Readable: " . (is_readable($localPath) ? 'Yes' : 'No') . "\n";
