<?php
$baseDir = realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR;
echo "Base Dir: $baseDir\n";

$relPath = 'gallery/assets/asd-gallery/1.png'; // fake path, might not exist exactly
// let's grab a real file
$galleries = glob($baseDir . 'gallery/assets/*/*.*');
if (empty($galleries)) {
    echo "No files found to test.\n";
    exit;
}

$testFile = $galleries[0];
$relPath = str_replace($baseDir, '', $testFile);
echo "Testing with RelPath: $relPath\n";

$relPathClean = ltrim(str_replace('..', '', $relPath), '/\\');
$fullPath = realpath($baseDir . $relPathClean);

echo "Cleaned RelPath: $relPathClean\n";
echo "Resolved FullPath: " . ($fullPath ? $fullPath : "FALSE (file might not exist)") . "\n";

if (!$fullPath || strpos($fullPath, $baseDir) !== 0) {
    echo "ERROR: Blocked by security check.\n";
    echo "  !fullPath: " . (!$fullPath ? 'true' : 'false') . "\n";
    echo "  strpos != 0: " . (strpos($fullPath, $baseDir) !== 0 ? 'true' : 'false') . " (strpos returned: " . var_export(strpos($fullPath, $baseDir), true) . ")\n";
} else {
    echo "SUCCESS: Passes security check.\n";
    
    $allowed = [
        $baseDir . 'gallery' . DIRECTORY_SEPARATOR . 'assets',
        $baseDir . 'assets' . DIRECTORY_SEPARATOR . 'portfolio'
    ];
    $inAllowed = false;
    foreach ($allowed as $a) {
        if (strpos($fullPath, realpath($a)) === 0) {
            $inAllowed = true;
            echo "Matched allowed path: " . realpath($a) . "\n";
            break;
        }
    }
    if (!$inAllowed) {
        echo "ERROR: Blocked by allowed directories check.\n";
    } else {
        echo "SUCCESS: Passes all checks. Ready to unlink.\n";
    }
}
