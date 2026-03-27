<?php
// gallery/api/final-verify.php

// Define a test output path
$test_output = __DIR__ . '/final-verify.jpg';

// We want to test get-image.php logic without sessions
// We'll mock the variables get-image.php expects if we were to include it,
// but it calls session_start() which might fail in CLI.
// So we'll just run it via CLI with a mocked session file if needed,
// or better yet, just use our standalone logic which we just synced with get-image.php.

// Let's just run get-image.php and suppress the session error or handle it.
// Actually, in PHP 8+, session_start() in CLI just works but doesn't do much.

$_SESSION['authenticated'] = true;
$_SESSION['user'] = [
    'username' => 'demo',
    'folder' => 'demo'
];
$_GET['f'] = 'demo';
$_GET['i'] = '1.jpg';

ob_start();
try {
    @include __DIR__ . '/get-image.php';
} catch (Error $e) {
    echo "Caught: " . $e->getMessage() . "\n";
}
$image_data = ob_get_clean();

if (strlen($image_data) > 100) {
    file_put_contents($test_output, $image_data);
    echo "Success! Generated image size: " . strlen($image_data) . " bytes\n";
    echo "Saved to: $test_output\n";
} else {
    echo "Failed! Image data too small (" . strlen($image_data) . " bytes)\n";
    echo "Output was: " . substr($image_data, 0, 100) . "...\n";
}
?>
