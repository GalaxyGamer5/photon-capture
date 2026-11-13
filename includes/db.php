<?php
// includes/db.php
// Centralized Database Abstraction Layer for Photon Capture

require_once __DIR__ . '/config.php';

/** Returns the filesystem path to the SQLite .db file (or null for MySQL). */
function getDBPath(): ?string {
    $dbType = defined('DB_TYPE') ? strtolower(DB_TYPE) : 'sqlite';
    if ($dbType !== 'sqlite') return null;
    return defined('DB_SQLITE_PATH') ? DB_SQLITE_PATH : (__DIR__ . '/../data/photon.db');
}

function getDB(bool $forceNew = false) {
    static $pdo = null;
    if ($pdo !== null && !$forceNew) {
        return $pdo;
    }
    if ($forceNew) {
        $pdo = null; // reset static cache
    }

    $dbType = defined('DB_TYPE') ? strtolower(DB_TYPE) : 'sqlite';

    if ($dbType === 'mysql') {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } else {
        // Default to SQLite
        $dbPath = DB_SQLITE_PATH;
        $dbDir = dirname($dbPath);
        if (!is_dir($dbDir)) {
            @mkdir($dbDir, 0777, true);
        }
        @chmod($dbDir, 0777);

        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA journal_mode = WAL;');
        $pdo->exec('PRAGMA foreign_keys = ON;');

        if (file_exists($dbPath)) {
            @chmod($dbPath, 0666);
        }
    }

    // Auto-initialize schema & migrate if first run
    initSchema($pdo);

    return $pdo;
}

function initSchema(PDO $pdo) {
    static $initialized = false;
    if ($initialized) return;

    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $isMysql = ($driver === 'mysql');

    $autoInc = $isMysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $longText = $isMysql ? 'LONGTEXT' : 'TEXT';

    // 1. Users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id $autoInc,
        username VARCHAR(100) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        name VARCHAR(255) NOT NULL,
        folder VARCHAR(255) NOT NULL,
        image_count INT DEFAULT 0,
        is_protected INT DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Favorites table
    $pdo->exec("CREATE TABLE IF NOT EXISTS favorites (
        id $autoInc,
        username VARCHAR(100) NOT NULL,
        filename VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (username, filename)
    )");

    // 3. Portfolio table
    $pdo->exec("CREATE TABLE IF NOT EXISTS portfolio (
        id VARCHAR(100) PRIMARY KEY,
        filename VARCHAR(255) NOT NULL,
        category VARCHAR(100) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 4. Inquiries table
    $pdo->exec("CREATE TABLE IF NOT EXISTS inquiries (
        id VARCHAR(100) PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        service VARCHAR(255),
        message $longText,
        unread INT DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 5. Orders table
    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        order_id VARCHAR(100) PRIMARY KEY,
        customer_name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(100),
        booking_date VARCHAR(50),
        service VARCHAR(100),
        duration INT DEFAULT 0,
        extras $longText,
        total_amount DECIMAL(10,2) DEFAULT 0,
        deposit_amount DECIMAL(10,2) DEFAULT 0,
        deposit_paid INT DEFAULT 0,
        deposit_paid_date VARCHAR(50),
        deposit_payment_method VARCHAR(50),
        final_payment_unlocked INT DEFAULT 0,
        final_amount DECIMAL(10,2) DEFAULT 0,
        final_paid INT DEFAULT 0,
        final_paid_date VARCHAR(50),
        final_payment_method VARCHAR(50),
        notes $longText,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 6. Calendar events table
    $pdo->exec("CREATE TABLE IF NOT EXISTS calendar_events (
        event_date VARCHAR(50) PRIMARY KEY,
        status VARCHAR(50) NOT NULL DEFAULT 'available',
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 7. Testimonials table
    $pdo->exec("CREATE TABLE IF NOT EXISTS testimonials (
        id VARCHAR(100) PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        rating INT DEFAULT 5,
        text $longText NOT NULL,
        date VARCHAR(50),
        approved INT DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 8. FAQ table
    $pdo->exec("CREATE TABLE IF NOT EXISTS faq (
        id VARCHAR(100) PRIMARY KEY,
        question_de $longText NOT NULL,
        question_en $longText,
        answer_de $longText NOT NULL,
        answer_en $longText,
        sort_order INT DEFAULT 0
    )");

    // 9. App settings (e.g. pricing, general config)
    $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
        key_name VARCHAR(100) PRIMARY KEY,
        value $longText NOT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 10. Galleries table (Multi-gallery support per user)
    $pdo->exec("CREATE TABLE IF NOT EXISTS galleries (
        id $autoInc,
        user_id INT NOT NULL,
        title VARCHAR(150) NOT NULL DEFAULT 'Hauptgalerie',
        folder VARCHAR(150) NOT NULL UNIQUE,
        image_count INT DEFAULT 0,
        is_protected INT DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 11. Chat messages table (Client <-> Photographer communication)
    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_messages (
        id $autoInc,
        user_id INT NOT NULL,
        sender VARCHAR(20) NOT NULL,
        sender_name VARCHAR(100),
        message $longText NOT NULL,
        is_read INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // One-time initial migration from existing legacy JSON files
    migrateLegacyData($pdo);

    $initialized = true;
}

function migrateLegacyData(PDO $pdo) {
    $baseDir = dirname(__DIR__);

    // --- Migrate Pricing ---
    $pricingCount = $pdo->query("SELECT COUNT(*) FROM app_settings WHERE key_name = 'pricing'")->fetchColumn();
    if ($pricingCount == 0) {
        $pricingFile = $baseDir . '/data/pricing.json';
        if (file_exists($pricingFile)) {
            $raw = file_get_contents($pricingFile);
            if ($raw && json_decode($raw)) {
                $stmt = $pdo->prepare("INSERT INTO app_settings (key_name, value) VALUES ('pricing', ?)");
                $stmt->execute([$raw]);
            }
        }
    }

    // --- Migrate FAQ ---
    $faqCount = $pdo->query("SELECT COUNT(*) FROM faq")->fetchColumn();
    if ($faqCount == 0) {
        $faqFile = $baseDir . '/data/faq.json';
        if (file_exists($faqFile)) {
            $data = json_decode(file_get_contents($faqFile), true);
            if (isset($data['items']) && is_array($data['items'])) {
                $stmt = $pdo->prepare("INSERT INTO faq (id, question_de, question_en, answer_de, answer_en, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
                $order = 0;
                foreach ($data['items'] as $item) {
                    $order++;
                    $stmt->execute([
                        $item['id'] ?? uniqid('faq_'),
                        $item['q']['de'] ?? '',
                        $item['q']['en'] ?? '',
                        $item['a']['de'] ?? '',
                        $item['a']['en'] ?? '',
                        $order
                    ]);
                }
            }
        }
    }

    // --- Migrate Testimonials ---
    $testCount = $pdo->query("SELECT COUNT(*) FROM testimonials")->fetchColumn();
    if ($testCount == 0) {
        $testFile = $baseDir . '/data/testimonials.json';
        if (file_exists($testFile)) {
            $data = json_decode(file_get_contents($testFile), true);
            if (isset($data['testimonials']) && is_array($data['testimonials'])) {
                $stmt = $pdo->prepare("INSERT INTO testimonials (id, name, rating, text, date, approved) VALUES (?, ?, ?, ?, ?, ?)");
                foreach ($data['testimonials'] as $t) {
                    $stmt->execute([
                        $t['id'] ?? uniqid('test_'),
                        $t['name'] ?? 'Anonym',
                        $t['rating'] ?? 5,
                        $t['text'] ?? '',
                        $t['date'] ?? date('Y-m-d'),
                        !empty($t['approved']) ? 1 : 1
                    ]);
                }
            }
        }
    }

    // --- Migrate Inquiries ---
    $inqCount = $pdo->query("SELECT COUNT(*) FROM inquiries")->fetchColumn();
    if ($inqCount == 0) {
        $inqFile = $baseDir . '/data/inquiries.json';
        if (file_exists($inqFile)) {
            $data = json_decode(file_get_contents($inqFile), true);
            $list = $data['inquiries'] ?? (is_array($data) ? $data : []);
            if (is_array($list)) {
                $stmt = $pdo->prepare("INSERT INTO inquiries (id, name, email, service, message, unread, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
                foreach ($list as $inq) {
                    if (empty($inq['id'])) continue;
                    $stmt->execute([
                        $inq['id'],
                        $inq['name'] ?? '',
                        $inq['email'] ?? '',
                        $inq['service'] ?? '',
                        $inq['message'] ?? '',
                        !empty($inq['unread']) ? 1 : 0,
                        $inq['date'] ?? date('Y-m-d H:i:s')
                    ]);
                }
            }
        }
    }

    // --- Migrate Calendar ---
    $calCount = $pdo->query("SELECT COUNT(*) FROM calendar_events")->fetchColumn();
    if ($calCount == 0) {
        $calFile = $baseDir . '/data/calendar.json';
        if (file_exists($calFile)) {
            $data = json_decode(file_get_contents($calFile), true);
            if (is_array($data)) {
                $stmt = $pdo->prepare("INSERT OR REPLACE INTO calendar_events (event_date, status) VALUES (?, ?)");
                foreach ($data as $date => $info) {
                    $status = is_array($info) ? ($info['status'] ?? 'available') : (string)$info;
                    $stmt->execute([$date, $status]);
                }
            }
        }
    }

    // --- Migrate Portfolio ---
    $portCount = $pdo->query("SELECT COUNT(*) FROM portfolio")->fetchColumn();
    if ($portCount == 0) {
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO portfolio (id, filename, category, created_at) VALUES (?, ?, ?, ?)");

        // 1. Migrate from portfolio.json if it has entries
        $portFile = $baseDir . '/data/portfolio.json';
        $migratedFromJson = false;
        if (file_exists($portFile)) {
            $data = json_decode(file_get_contents($portFile), true);
            if (isset($data['images']) && is_array($data['images']) && count($data['images']) > 0) {
                foreach ($data['images'] as $img) {
                    if (empty($img['filename'])) continue;
                    $stmt->execute([
                        $img['id'] ?? uniqid('img_'),
                        $img['filename'],
                        $img['category'] ?? 'portrait',
                        $img['date'] ?? date('Y-m-d H:i:s')
                    ]);
                }
                $migratedFromJson = true;
            }
        }

        // 2. If JSON was empty, scan filesystem for existing portfolio images
        if (!$migratedFromJson) {
            $portDir = $baseDir . '/assets/portfolio/';
            if (is_dir($portDir)) {
                $allowed = ['jpg','jpeg','png','webp','gif'];
                $files = scandir($portDir);
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') continue;
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowed)) continue;
                    // Try to guess category from filename prefix
                    $cat = 'portrait';
                    $lc = strtolower($file);
                    if (strpos($lc, 'event') !== false) $cat = 'event';
                    elseif (strpos($lc, 'pet') !== false || strpos($lc, 'tier') !== false) $cat = 'pet';
                    $mtime = filemtime($portDir . $file);
                    $stmt->execute([
                        uniqid('img_'),
                        $file,
                        $cat,
                        date('Y-m-d H:i:s', $mtime ?: time())
                    ]);
                }
            }
        }
    }

    // --- Migrate Users ---
    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($userCount == 0) {
        $usersFile = $baseDir . '/gallery/data/users.js';
        if (file_exists($usersFile)) {
            $content = file_get_contents($usersFile);
            if (preg_match('/window\.usersDatabase\s*=\s*({[\s\S]*?});/', $content, $m)) {
                $data = json_decode($m[1], true);
                if (isset($data['users']) && is_array($data['users'])) {
                    $stmt = $pdo->prepare("INSERT OR IGNORE INTO users (username, password_hash, name, folder, image_count, is_protected) VALUES (?, ?, ?, ?, ?, ?)");
                    foreach ($data['users'] as $u) {
                        if (empty($u['username']) || $u['username'] === 'demo') continue; // Skip test user
                        $stmt->execute([
                            $u['username'],
                            $u['passwordHash'] ?? '',
                            $u['name'] ?? $u['username'],
                            $u['folder'] ?? ($u['username'] . '-gallery'),
                            $u['imageCount'] ?? 0,
                            isset($u['isProtected']) ? ($u['isProtected'] ? 1 : 0) : 1
                        ]);
                    }
                }
            }
        }
    }

    // --- Migrate Favorites ---
    $favCount = $pdo->query("SELECT COUNT(*) FROM favorites")->fetchColumn();
    if ($favCount == 0) {
        $favFile = $baseDir . '/gallery/data/favorites.json';
        if (file_exists($favFile)) {
            $data = json_decode(file_get_contents($favFile), true);
            if (is_array($data)) {
                $stmt = $pdo->prepare("INSERT OR IGNORE INTO favorites (username, filename) VALUES (?, ?)");
                foreach ($data as $username => $images) {
                    if (is_array($images)) {
                        foreach ($images as $img) {
                            $stmt->execute([$username, $img]);
                        }
                    }
                }
            }
        }
    }

    // --- Ensure every user has at least one gallery entry in `galleries` ---
    $uStmt = $pdo->query("SELECT id, name, folder, image_count, is_protected FROM users");
    $allUsers = $uStmt->fetchAll();
    $gCheck = $pdo->prepare("SELECT COUNT(*) FROM galleries WHERE user_id = ?");
    $gInsert = $pdo->prepare("INSERT OR IGNORE INTO galleries (user_id, title, folder, image_count, is_protected) VALUES (?, ?, ?, ?, ?)");
    foreach ($allUsers as $u) {
        $gCheck->execute([$u['id']]);
        if ($gCheck->fetchColumn() == 0 && !empty($u['folder'])) {
            $gInsert->execute([
                $u['id'],
                'Hauptgalerie',
                $u['folder'],
                (int)($u['image_count'] ?? 0),
                (int)($u['is_protected'] ?? 1)
            ]);
        }
    }
}

// Helper: sync SQL users table to gallery/data/users.js for frontend compatibility
function syncUsersJs(PDO $pdo) {
    $stmt = $pdo->query("SELECT id, username, password_hash, name, folder, image_count, is_protected FROM users ORDER BY id ASC");
    $rows = $stmt->fetchAll();

    $galStmt = $pdo->prepare("SELECT id, title, folder, image_count, is_protected, created_at FROM galleries WHERE user_id = ? ORDER BY id ASC");

    $baseAssetsDir = dirname(__DIR__) . '/gallery/assets/';
    $updG = $pdo->prepare("UPDATE galleries SET image_count = ? WHERE id = ?");
    $updU = $pdo->prepare("UPDATE users SET image_count = ? WHERE id = ?");

    $users = [];
    foreach ($rows as $r) {
        $galStmt->execute([$r['id']]);
        $userGalleries = $galStmt->fetchAll();

        // If no galleries in DB yet, synthesize default from user record
        if (empty($userGalleries) && !empty($r['folder'])) {
            $userGalleries = [[
                'id' => 0,
                'title' => 'Hauptgalerie',
                'folder' => $r['folder'],
                'image_count' => (int)$r['image_count'],
                'is_protected' => (bool)$r['is_protected'],
                'created_at' => date('Y-m-d H:i:s')
            ]];
        }

        $processedGals = [];
        foreach ($userGalleries as $g) {
            $folderPath = $baseAssetsDir . $g['folder'];
            $diskCount = 0;
            if (is_dir($folderPath)) {
                foreach (scandir($folderPath) as $f) {
                    if ($f !== '.' && $f !== '..' && $f !== '_thumbs' && preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $f)) {
                        $diskCount++;
                    }
                }
            }
            $finalCount = ($diskCount > 0) ? $diskCount : (int)$g['image_count'];
            if ($diskCount > 0 && $diskCount !== (int)$g['image_count'] && !empty($g['id'])) {
                try { $updG->execute([$diskCount, $g['id']]); } catch(Exception $e){}
            }

            $processedGals[] = [
                'id' => (int)$g['id'],
                'title' => $g['title'],
                'folder' => $g['folder'],
                'imageCount' => $finalCount,
                'isProtected' => (bool)$g['is_protected']
            ];
        }

        $totalUserCount = array_sum(array_column($processedGals, 'imageCount'));
        if ($totalUserCount > 0 && $totalUserCount !== (int)$r['image_count']) {
            try { $updU->execute([$totalUserCount, $r['id']]); } catch(Exception $e){}
        }

        $users[] = [
            'id' => (string)$r['id'],
            'username' => $r['username'],
            'passwordHash' => $r['password_hash'],
            'name' => $r['name'],
            'folder' => $r['folder'],
            'imageCount' => $totalUserCount ?: (int)$r['image_count'],
            'isProtected' => (bool)$r['is_protected'],
            'galleries' => $processedGals
        ];
    }

    $data = ['users' => $users];
    $jsContent = "// Client-side user database\n";
    $jsContent .= "// Synchronized from SQLite/MySQL database\n";
    $jsContent .= "window.usersDatabase = " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . ";\n";

    $file = dirname(__DIR__) . '/gallery/data/users.js';
    @file_put_contents($file, $jsContent);
    @chmod($file, 0777);
}

