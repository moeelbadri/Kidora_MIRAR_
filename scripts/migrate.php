<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../database/migrations.php';

// Run offline (with traffic stopped). Runtime credentials need no DDL privileges.
function kidora_migration_env(string $name, string $fallback): string {
    $value = getenv('KIDAURA_MIGRATION_DB_' . $name);
    if ($value === false) $value = getenv('KIDORA_MIGRATION_DB_' . $name);
    return $value === false ? $fallback : $value;
}
try {
    if (DB_DRIVER === 'sqlite' && !is_dir(dirname(SQLITE_PATH))) {
        if (!mkdir(dirname(SQLITE_PATH), 0700, true) && !is_dir(dirname(SQLITE_PATH))) throw new RuntimeException('Cannot create database directory');
    }
    $pdo = new PDO(DB_DRIVER === 'mysql'
        ? 'mysql:host=' . kidora_migration_env('HOST', DB_HOST) . ';dbname=' . kidora_migration_env('NAME', DB_NAME) . ';charset=utf8mb4'
        : 'sqlite:' . SQLITE_PATH,
        DB_DRIVER === 'mysql' ? kidora_migration_env('USER', DB_USER) : null,
        DB_DRIVER === 'mysql' ? kidora_migration_env('PASS', DB_PASS) : null,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    if (DB_DRIVER === 'sqlite') { $pdo->exec('PRAGMA foreign_keys = ON'); $pdo->exec('PRAGMA busy_timeout = 10000'); }
    kidora_run_migrations($pdo);
    if (DB_DRIVER === 'sqlite') chmod(SQLITE_PATH, 0600);
    fwrite(STDOUT, "Database migrations up to date (version 2).\n");
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . "\n");
    exit(1);
}
