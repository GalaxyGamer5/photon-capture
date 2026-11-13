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

require_once __DIR__ . '/../../includes/db.php';

$baseDir = realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR;
$dataDir = $baseDir . 'gallery' . DIRECTORY_SEPARATOR . 'data';
$assetsDir = $baseDir . 'gallery' . DIRECTORY_SEPARATOR . 'assets';
$usersFile = $dataDir . DIRECTORY_SEPARATOR . 'users.js';
$backupFile = $dataDir . DIRECTORY_SEPARATOR . 'users_backup.json';
$backupsDir = $dataDir . DIRECTORY_SEPARATOR . 'backups';

$action = $_GET['action'] ?? ($_POST['action'] ?? 'list');

function getKnownUserFolders() {
    $folders = [];
    try {
        $db = getDB();
        $stmt = $db->query("SELECT folder FROM users");
        $folders = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {}

    // Also check users.js
    global $usersFile;
    if (file_exists($usersFile)) {
        $content = @file_get_contents($usersFile);
        if (preg_match('/window\.usersDatabase\s*=\s*({[\s\S]*?});/', $content, $m)) {
            $data = json_decode($m[1], true);
            if (!empty($data['users'])) {
                foreach ($data['users'] as $u) {
                    if (!empty($u['folder'])) $folders[] = $u['folder'];
                }
            }
        }
    }
    return array_unique($folders);
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

// 2. Scan gallery/assets/ for folders that exist on disk but are not in database
if ($action === 'scan_folders') {
    $knownFolders = getKnownUserFolders();

    $orphanFolders = [];
    if (is_dir($assetsDir)) {
        $items = scandir($assetsDir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === 'demo') continue;
            $itemPath = $assetsDir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($itemPath)) {
                $files = scandir($itemPath);
                $imageCount = 0;
                foreach ($files as $f) {
                    if (preg_match('/\.(jpe?g|png|webp)$/i', $f)) {
                        $imageCount++;
                    }
                }

                $isKnown = in_array($item, $knownFolders);
                if (!$isKnown) {
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
    if (!$data || !isset($data['users']) || !is_array($data['users'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ungültiges Backup-Format.']);
        exit;
    }

    try {
        $db = getDB();
        $db->beginTransaction();

        $stmt = $db->prepare("INSERT INTO users (username, password_hash, name, folder, image_count, is_protected)
            VALUES (?, ?, ?, ?, ?, ?)
            ON CONFLICT(username) DO UPDATE SET
                password_hash = excluded.password_hash,
                name = excluded.name,
                folder = excluded.folder,
                image_count = excluded.image_count,
                is_protected = excluded.is_protected");

        foreach ($data['users'] as $u) {
            if (empty($u['username'])) continue;
            $stmt->execute([
                $u['username'],
                $u['passwordHash'] ?? '',
                $u['name'] ?? $u['username'],
                $u['folder'] ?? ($u['username'] . '-gallery'),
                (int)($u['imageCount'] ?? 0),
                isset($u['isProtected']) ? ($u['isProtected'] ? 1 : 0) : 1
            ]);
        }

        $db->commit();
        syncUsersJs($db);

        echo json_encode(['success' => true, 'userCount' => count($data['users'])]);
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) $db->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'DB Restore error: ' . $e->getMessage()]);
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
        echo json_encode(['success' => false, 'message' => 'Alle Felder sind erforderlich.']);
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

    try {
        $db = getDB();

        // Check if username already exists
        $chk = $db->prepare("SELECT id FROM users WHERE LOWER(username) = LOWER(?)");
        $chk->execute([$username]);
        if ($chk->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Dieser Benutzername ist bereits vergeben.']);
            exit;
        }

        $stmt = $db->prepare("INSERT INTO users (username, password_hash, name, folder, image_count, is_protected) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->execute([
            $username,
            sha1($password),
            $name,
            $folder,
            $imageCount
        ]);

        syncUsersJs($db);

        echo json_encode(['success' => true, 'user' => [
            'username' => $username,
            'name' => $name,
            'folder' => $folder,
            'imageCount' => $imageCount
        ]]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion']);
