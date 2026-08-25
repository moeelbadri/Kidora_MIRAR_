<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';

/**
 * يُنشئ اتصال PDO حسب DB_DRIVER المحدد في config.php.
 * في وضع SQLite: يُنشئ قاعدة البيانات والجداول والبيانات الأولية
 * تلقائياً عند أول تشغيل، بدون أي إعداد يدوي.
 */
function kidaura_connect(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    if (DB_DRIVER === 'mysql') {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    }

    // ---------------- SQLite (تجربة سريعة محلياً) ----------------
    $storageDir = dirname(SQLITE_PATH);
    if (!is_dir($storageDir)) mkdir($storageDir, 0777, true);

    $isNew = !file_exists(SQLITE_PATH);
    $pdo = new PDO('sqlite:' . SQLITE_PATH, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');

    if ($isNew) {
        $schema = file_get_contents(__DIR__ . '/../database/schema_sqlite.sql');
        $pdo->exec($schema);
        require_once __DIR__ . '/../database/seed.php';
        kidora_seed($pdo);
    }

    return $pdo;
}

$pdo = kidaura_connect();
