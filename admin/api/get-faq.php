<?php
session_start();
header('Content-Type: application/json');

$file = __DIR__ . '/../../data/faq.json';

if (file_exists($file)) {
    echo file_get_contents($file);
} else {
    echo json_encode(['error' => 'FAQ file not found']);
}
