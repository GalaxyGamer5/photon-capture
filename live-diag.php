<?php
// Strict diagnostic to find users.js
header('Content-Type: text/plain');

$paths = [
    __DIR__ . '/data/users.js',
    __DIR__ . '/gallery/data/users.js',
    '/usr/share/nginx/html/photon-capture/data/users.js',
    '/usr/share/nginx/html/photon-capture/gallery/data/users.js'
];

echo "Looking for users.js...\n";
foreach ($paths as $p) {
    if (file_exists($p)) {
        echo "FOUND: $p\n";
        $content = file_get_contents($p);
        preg_match('/window\.usersDatabase\s*=\s*({[\s\S]*?});/', $content, $matches);
        if (isset($matches[1])) {
            echo "REGEX OK! Parsed user database.\n";
            $data = json_decode($matches[1], true);
            foreach ($data['users'] as $u) {
                if ($u['username'] === 'Robin Morgenstern') {
                    echo "Robin isProtected: " . (isset($u['isProtected']) && $u['isProtected'] ? 'TRUE' : 'FALSE') . "\n";
                }
            }
        }
    } else {
        echo "MISSING: $p\n";
    }
}
?>
