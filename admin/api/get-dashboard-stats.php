<?php
session_start();
header('Content-Type: application/json');



$stats = [
    'inquiries' => 0,
    'galleries' => 0,
    'portfolio' => 0,
    'testimonials' => 0,
    'favorites' => 0
];

// 1. Inquiries
$inqFile = __DIR__ . '/../../data/inquiries.json';
if (file_exists($inqFile)) {
    $data = json_decode(file_get_contents($inqFile), true);
    if (is_array($data)) $stats['inquiries'] = count($data);
}

// 2. Galleries / Users
$usersFile = __DIR__ . '/../../gallery/data/users.js';
if (file_exists($usersFile)) {
    $content = file_get_contents($usersFile);
    if (preg_match('/window\.usersDatabase\s*=\s*({[\s\S]*?});/', $content, $matches)) {
        $usersData = json_decode($matches[1], true);
        if (isset($usersData['users'])) {
            $stats['galleries'] = count($usersData['users']);
        }
    }
}

// 3. Portfolio
$portFile = __DIR__ . '/../../data/portfolio.json';
if (file_exists($portFile)) {
    $data = json_decode(file_get_contents($portFile), true);
    if (is_array($data)) $stats['portfolio'] = count($data);
}

// 4. Testimonials
$testFile = __DIR__ . '/../../data/testimonials.json';
if (file_exists($testFile)) {
    $data = json_decode(file_get_contents($testFile), true);
    if (is_array($data)) $stats['testimonials'] = count($data);
}

// 5. Total Favorites
$favFile = __DIR__ . '/../../gallery/data/favorites.json';
if (file_exists($favFile)) {
    $data = json_decode(file_get_contents($favFile), true);
    if (is_array($data)) {
        foreach ($data as $userFavs) {
            $stats['favorites'] += count($userFavs);
        }
    }
}

echo json_encode($stats);
