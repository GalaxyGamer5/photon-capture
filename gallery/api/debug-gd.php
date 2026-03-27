<?php
// gallery/api/debug-gd.php
header('Content-Type: text/plain');

echo "--- PHP GD Check ---\n";
echo "GD extension loaded: " . (extension_loaded('gd') ? "YES" : "NO") . "\n";
echo "imagecreatefromjpeg exists: " . (function_exists('imagecreatefromjpeg') ? "YES" : "NO") . "\n";
echo "imagecreatefrompng exists: " . (function_exists('imagecreatefrompng') ? "YES" : "NO") . "\n";
echo "imagefilter exists: " . (function_exists('imagefilter') ? "YES" : "NO") . "\n";

echo "\n--- Protection Check ---\n";
$usersFile = __DIR__ . '/../data/users.js';
echo "users.js exists: " . (file_exists($usersFile) ? "YES" : "NO") . "\n";

if (file_exists($usersFile)) {
    $usersContent = file_get_contents($usersFile);
    echo "users.js content length: " . strlen($usersContent) . " bytes\n";
    
    preg_match('/window\.usersDatabase\s*=\s*({[\s\S]*?});/', $usersContent, $matches);
    if (isset($matches[1])) {
        echo "Regex match successful\n";
        $usersData = json_decode($matches[1], true);
        if ($usersData === null) {
            echo "JSON decode failed: " . json_last_error_msg() . "\n";
        } else {
            echo "JSON decode successful. Users found: " . count($usersData['users']) . "\n";
            foreach ($usersData['users'] as $u) {
                echo "- User: " . $u['username'] . ", isProtected: " . (isset($u['isProtected']) ? ($u['isProtected'] ? "TRUE" : "FALSE") : "NOT SET (Default: TRUE)") . "\n";
            }
        }
    } else {
        echo "Regex match failed\n";
    }
}
?>
