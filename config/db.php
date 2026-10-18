<?php
require_once __DIR__ . '/config.php';

/** Runtime connections deliberately never initialize or upgrade a database. */
function kidaura_connect(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    try {
        if (DB_DRIVER === 'sqlite' && !is_file(SQLITE_PATH)) {
            throw new RuntimeException('Database file missing');
        }
        $db = new PDO(DB_DRIVER === 'mysql'
            ? 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4'
            : 'sqlite:' . SQLITE_PATH, DB_DRIVER === 'mysql' ? DB_USER : null,
            DB_DRIVER === 'mysql' ? DB_PASS : null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        if (DB_DRIVER === 'sqlite') {
            $db->exec('PRAGMA foreign_keys = ON');
            $db->exec('PRAGMA busy_timeout = 10000');
        }
        $versions = $db->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
        if (array_diff(['1', '2'], array_map('strval', $versions))) {
            throw new RuntimeException('Database migrations missing');
        }
        return $pdo = $db;
    } catch (Throwable $e) {
        error_log('Kidora database unavailable: ' . $e->getMessage());
        http_response_code(503);
        exit('Database unavailable or migrations missing. Run php scripts/migrate.php before serving requests.');
    }
}

/** Serialize child-owned mutations; SQLite takes its write lock before any reads. */
function kidora_lock_child(PDO $pdo, int $id): array {
    $pdo->beginTransaction();
    try {
        if (DB_DRIVER === 'sqlite') {
            $pdo->prepare('UPDATE children SET id = id WHERE id = ?')->execute([$id]);
        }
        $st = $pdo->prepare('SELECT * FROM children WHERE id = ?' . (DB_DRIVER === 'mysql' ? ' FOR UPDATE' : ''));
        $st->execute([$id]);
        $child = $st->fetch();
        if (!$child) throw new RuntimeException('Child no longer exists');
        return $child;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

$pdo = kidaura_connect();
if (PHP_SAPI !== 'cli') kidora_authenticate_session($pdo);
