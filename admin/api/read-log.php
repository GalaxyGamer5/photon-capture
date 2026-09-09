<?php
header('Content-Type: text/plain');
$log = __DIR__ . '/upload-debug.log';
if (file_exists($log)) {
    echo file_get_contents($log);
} else {
    echo "No log file found.";
}
