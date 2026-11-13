<?php
// includes/config.php
// Database configuration for Photon Capture

// DB Driver: 'sqlite' or 'mysql'
// By default, 'sqlite' works instantly out-of-the-box on standard PHP hosting with zero setup.
// If you want to use MySQL/MariaDB from your hoster, simply change DB_TYPE to 'mysql' and fill in your credentials below.
if (!defined('DB_TYPE')) {
    define('DB_TYPE', 'sqlite');
}

// Path to SQLite database file
if (!defined('DB_SQLITE_PATH')) {
    define('DB_SQLITE_PATH', __DIR__ . '/../data/photon.db');
}

// MySQL / MariaDB Settings (optional):
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_PORT')) define('DB_PORT', 3306);
if (!defined('DB_NAME')) define('DB_NAME', 'photon_capture');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

