<?php
// gallery/api/test-watermark.php

// Mock session
$_SESSION['authenticated'] = true;
$_SESSION['user'] = [
    'username' => 'demo',
    'folder' => 'demo'
];

$_GET['f'] = 'demo';
$_GET['i'] = '1.jpg';

// Define a test output path
$test_output = __DIR__ . '/test-output.jpg';

// Capture output
ob_start();
include __DIR__ . '/get-image.php';
$image_data = ob_get_clean();

// Check if we got something
if (strlen($image_data) > 0) {
    file_put_contents($test_output, $image_data);
    echo "Successfully generated test image: " . strlen($image_data) . " bytes\n";
    echo "Output saved to: " . $test_output . "\n";
} else {
    echo "Failed to generate image data. Check if get-image.php outputted anything.\n";
}

// Clean up session forCLI
session_destroy();
?>
