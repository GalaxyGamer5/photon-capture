<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$file = '../data/faq.json';

if (file_exists($file)) {
    echo file_get_contents($file);
} else {
    echo json_encode(['error' => 'FAQ file not found']);
}
