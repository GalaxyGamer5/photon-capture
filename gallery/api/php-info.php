<?php
header('Content-Type: text/plain');
echo "PHP Version: " . phpversion() . "\n";
echo "Loaded Configuration File (php.ini): " . php_ini_loaded_file() . "\n";
echo "Additional .ini files parsed: " . php_ini_scanned_files() . "\n";
echo "\nLoaded Extensions:\n";
print_r(get_loaded_extensions());
?>
