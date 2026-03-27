<?php
// Strict tracing diagnostic
header('Content-Type: text/plain');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$user = ['username' => 'Robin Morgenstern', 'folder' => 'Robin Morgenstern-gallery'];
$requested_folder = $user['folder'];
$requested_image = '1.jpg';

echo "1. Tracing execution flow...\n";

// Path resolution fallback test
$image_path = __DIR__ . '/../assets/' . $requested_folder . '/' . $requested_image;
if (!file_exists($image_path)) {
    echo "  - Fallback used for image_path\n";
    $image_path = __DIR__ . '/../gallery/assets/' . $requested_folder . '/' . $requested_image;
}
echo "  Image Path: $image_path (EXISTS: " . (file_exists($image_path) ? 'YES' : 'NO') . ")\n";

$usersFile = __DIR__ . '/../data/users.js';
if (!file_exists($usersFile)) {
    echo "  - Fallback used for usersFile\n";
    $usersFile = __DIR__ . '/../gallery/data/users.js';
}
echo "  Users File: $usersFile (EXISTS: " . (file_exists($usersFile) ? 'YES' : 'NO') . ")\n";

$isProtected = false;
if (file_exists($usersFile)) {
    $usersContent = file_get_contents($usersFile);
    preg_match('/window\.usersDatabase\s*=\s*({[\s\S]*?});/', $usersContent, $matches);
    if (isset($matches[1])) {
        $usersData = json_decode($matches[1], true);
        if ($usersData && isset($usersData['users'])) {
            foreach ($usersData['users'] as $u) {
                if ($u['username'] === $user['username']) {
                    $isProtected = isset($u['isProtected']) ? (bool)$u['isProtected'] : true;
                    echo "  User matched! isProtected = " . ($isProtected ? 'TRUE' : 'FALSE') . "\n";
                    break;
                }
            }
        }
    }
}

echo "2. GD processing test...\n";
if (!function_exists('imagecreatefromjpeg')) {
    echo "  FATAL: GD imagecreatefromjpeg does not exist!\n";
} else {
    echo "  GD functions exist.\n";
}

$info = @getimagesize($image_path);
$mime = $info['mime'] ?? 'image/jpeg';
echo "  Detected MIME: $mime\n";

$image = @imagecreatefromjpeg($image_path);
if (!$image) {
    echo "  FATAL: Failed to create image resource from JPEG!\n";
} else {
    echo "  Image resource created successfully.\n";
    if ($isProtected) {
        if (!function_exists('imagefilter')) {
            echo "  FATAL: imagefilter function missing!\n";
        } else {
            echo "  Applying imagefilter Gaussian Blur...\n";
            $res = imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);
            echo "  imagefilter result: " . ($res ? 'SUCCESS' : 'FAILED') . "\n";
        }
        
        $width = imagesx($image);
        echo "  Applying watermark logic to image of width $width...\n";
        
        $color_main = imagecolorallocatealpha($image, 255, 255, 255, 30);
        if ($color_main === false) echo "  FATAL: Failed to allocate alpha color!\n";
		
		ob_start();
		imagejpeg($image, null, 75);
		$out = ob_get_clean();
		echo "  Generated JPEG size: " . strlen($out) . " bytes\n";
    }
}

// Final static check of the api/get-image.php file
echo "\n3. Checking what is physically inside /api/get-image.php...\n";
$target = __DIR__ . '/api/get-image.php';
if (file_exists($target)) {
    $c = file_get_contents($target);
    echo "  Does it have fallback logic? " . (strpos($c, '/../gallery/assets/') !== false ? 'YES' : 'NO') . "\n";
    echo "  Does it have (int)(int) fix? " . (strpos($c, '(int)((int)') !== false ? 'YES' : 'NO') . "\n";
} else {
    echo "  /api/get-image.php DOES NOT EXIST in this directory context!\n";
}
?>
