<?php
// Offline copy, not an automatic destructive migration. Originals remain blocked by the web server.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
if (!in_array('--copy', $argv, true)) {
    exit("Stop traffic, back up the database, then run php scripts/private-storage.php --copy. Originals are retained; remove them only after verification.\n");
}
umask(0077);
if (!is_dir(KIDORA_PRIVATE_DIR) && !mkdir(KIDORA_PRIVATE_DIR, 0700, true)) throw new RuntimeException('Cannot create private directory');
$legacy = __DIR__ . '/../storage/kidora.sqlite';
if (DB_DRIVER === 'sqlite' && is_file($legacy) && !is_file(SQLITE_PATH)) {
    if (!is_dir(dirname(SQLITE_PATH))) mkdir(dirname(SQLITE_PATH), 0700, true);
    // SQLite backup through VACUUM INTO includes a committed WAL, unlike copying the main file alone.
    $source = new PDO('sqlite:' . $legacy);
    $source->exec('VACUUM INTO ' . $source->quote(SQLITE_PATH));
    chmod(SQLITE_PATH, 0600);
    echo "Copied legacy database.\n";
}
$uploads = __DIR__ . '/../uploads';
foreach (['photos', 'drawings'] as $kind) {
    if (!is_dir($uploads . '/' . $kind)) continue;
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploads . '/' . $kind, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if (!$file->isFile() || $file->isLink() || str_starts_with($file->getFilename(), '.')) continue;
        $relative = substr($file->getPathname(), strlen($uploads) + 1);
        $dest = KIDORA_PRIVATE_DIR . '/uploads/' . $relative;
        if (is_file($dest)) {
            if (hash_file('sha256', $dest) !== hash_file('sha256', $file->getPathname())) throw new RuntimeException('Conflicting private copy: ' . $relative);
            continue;
        }
        if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0700, true);
        if (!copy($file->getPathname(), $dest)) throw new RuntimeException('Copy failed');
        touch($dest, $file->getMTime());
        chmod($dest, 0600);
    }
}
echo "Private copy complete. Originals retained. Run scripts/migrate.php next with the same environment.\n";
