<?php
// admin/api/restore_users.php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$baseDir = realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR;
$dataDir = $baseDir . 'gallery' . DIRECTORY_SEPARATOR . 'data';
$assetsDir = $baseDir . 'gallery' . DIRECTORY_SEPARATOR . 'assets';
$usersFile = $dataDir . DIRECTORY_SEPARATOR . 'users.js';
$backupFile = $dataDir . DIRECTORY_SEPARATOR . 'users_backup.json';
$backupsDir = $dataDir . DIRECTORY_SEPARATOR . 'backups';

$action = $_GET['action'] ?? ($_POST['action'] ?? 'list');

// Helper to load current users
function getCurrentUsers($usersFile) {
    if (!file_exists($usersFile)) return ['users' => []];
    $content = @file_get_contents($usersFile);
    if (preg_match('/window\.usersDatabase\s*=\s*({[\s\S]*?});/', $content, $m)) {
        $data = json_decode($m[1], true);
        if ($data && isset($data['users'])) return $data;
    }
    return ['users' => []];
}

// 1. List existing backups
if ($action === 'list') {
    $backups = [];
    if (file_exists($backupFile)) {
        $content = @file_get_contents($backupFile);
        $json = json_decode($content, true);
        $count = isset($json['users']) ? count($json['users']) : 0;
        $backups[] = [
            'name' => 'Letztes Backup (users_backup.json)',
            'file' => 'users_backup.json',
            'time' => date('Y-m-d H:i:s', filemtime($backupFile)),
            'userCount' => $count
        ];
    }
    if (is_dir($backupsDir)) {
        $files = scandir($backupsDir, SCANDIR_SORT_DESCENDING);
        foreach ($files as $f) {
            if (preg_match('/^users_.*\.json$/', $f)) {
                $fPath = $backupsDir . DIRECTORY_SEPARATOR . $f;
                $content = @file_get_contents($fPath);
                $json = json_decode($content, true);
                $count = isset($json['users']) ? count($json['users']) : 0;
                $backups[] = [
                    'name' => $f,
                    'file' => 'backups/' . $f,
                    'time' => date('Y-m-d H:i:s', filemtime($fPath)),
                    'userCount' => $count
                ];
            }
        }
    }
    echo json_encode(['success' => true, 'backups' => $backups]);
    exit;
}

// 2. Scan gallery/assets/ for folders that exist on disk but are not in users.js
if ($action === 'scan_folders') {
    $current = getCurrentUsers($usersFile);
    $knownFolders = [];
    foreach ($current['users'] as $u) {
        if (!empty($u['folder'])) {
            $knownFolders[] = $u['folder'];
        }
    }

    $orphanFolders = [];
    if (is_dir($assetsDir)) {
        $items = scandir($assetsDir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === 'demo') continue;
            $itemPath = $assetsDir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($itemPath)) {
                // Count valid images
                $files = scandir($itemPath);
                $imageCount = 0;
                foreach ($files as $f) {
                    if (preg_match('/\.(jpe?g|png|webp)$/i', $f)) {
                        $imageCount++;
                    }
                }

                $isKnown = in_array($item, $knownFolders);
                if (!$isKnown) {
                    // Suggested clean name from folder name
                    $suggestedName = str_replace(['-gallery', '_gallery', '-', '_'], [' ', ' ', ' ', ' '], $item);
                    $suggestedName = ucwords(trim($suggestedName));
                    $suggestedUser = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', str_replace(['-gallery', '_gallery'], '', $item)));

                    $orphanFolders[] = [
                        'folder' => $item,
                        'imageCount' => $imageCount,
                        'suggestedName' => $suggestedName ?: $item,
                        'suggestedUsername' => $suggestedUser ?: $item
                    ];
                }
            }
        }
    }

    echo json_encode(['success' => true, 'orphanFolders' => $orphanFolders]);
    exit;
}

// 3. Restore from a backup file
if ($action === 'restore') {
    $input = json_decode(file_get_contents('php://input'), true);
    $targetFile = $input['file'] ?? '';

    if ($targetFile === 'users_backup.json') {
        $restorePath = $backupFile;
    } else {
        $clean = basename($targetFile);
        $restorePath = $backupsDir . DIRECTORY_SEPARATOR . $clean;
    }

    if (!file_exists($restorePath)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Backup-Datei nicht gefunden.']);
        exit;
    }

    $raw = file_get_contents($restorePath);
    $data = json_decode($raw, true);
    if (!$data || !isset($data['users'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ungültiges Backup-Format.']);
        exit;
    }

    $jsContent = "// Client-side user database\n";
    $jsContent .= "// In a real application, this would be a server-side database\n";
    $jsContent .= "window.usersDatabase = " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . ";\n";

    if (file_put_contents($usersFile, $jsContent)) {
        @chmod($usersFile, 0777);
        echo json_encode(['success' => true, 'userCount' => count($data['users'])]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Fehler beim Schreiben von users.js']);
    }
    exit;
}

// 4. Relink an orphan folder as a user
if ($action === 'relink_folder') {
    $input = json_decode(file_get_contents('php://input'), true);
    $folder = trim($input['folder'] ?? '');
    $name = trim($input['name'] ?? '');
    $username = trim($input['username'] ?? '');
    $password = trim($input['password'] ?? '');

    if (!$folder || !$name || !$username || !$password) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Alle Felder (Ordner, Name, Username, Passwort) sind erforderlich.']);
        exit;
    }

    $folderPath = $assetsDir . DIRECTORY_SEPARATOR . $folder;
    if (!is_dir($folderPath)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Ordner existiert nicht auf dem Server.']);
        exit;
    }

    // Count images
    $files = scandir($folderPath);
    $imageCount = 0;
    foreach ($files as $f) {
        if (preg_match('/\.(jpe?g|png|webp)$/i', $f)) {
            $imageCount++;
        }
    }

    $current = getCurrentUsers($usersFile);
    // Check if username already exists
    foreach ($current['users'] as $u) {
        if ($u['username'] === $username) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Dieser Benutzername ist bereits vergeben.']);
            exit;
        }
    }

    $newUser = [
        'id' => (string)time() . rand(100, 999),
        'username' => $username,
        'passwordHash' => sha1($password),
        'name' => $name,
        'folder' => $folder,
        'imageCount' => $imageCount,
        'isProtected' => true
    ];

    $current['users'][] = $newUser;

    $jsContent = "// Client-side user database\n";
    $jsContent .= "// In a real application, this would be a server-side database\n";
    $jsContent .= "window.usersDatabase = " . json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . ";\n";

    if (file_put_contents($usersFile, $jsContent)) {
        @chmod($usersFile, 0777);
        // Also update backup
        @file_put_contents($backupFile, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo json_encode(['success' => true, 'user' => $newUser]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Fehler beim Speichern in users.js']);
    }
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion']);
