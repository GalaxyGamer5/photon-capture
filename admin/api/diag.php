<?php
header('Content-Type: application/json');
echo json_encode([
    'gd_installed' => extension_loaded('gd'),
    'upload_max' => ini_get('upload_max_filesize'),
    'post_max' => ini_get('post_max_size')
]);
