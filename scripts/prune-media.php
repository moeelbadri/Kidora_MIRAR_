<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
$apply = in_array('--apply', $argv, true);
$cutoff = time() - KIDORA_MEDIA_RETENTION_DAYS * 86400;
$count = 0;
// Includes orphan files from interrupted writes or account cleanup failures.
$roots = [KIDORA_PRIVATE_DIR . '/uploads'];
if (in_array('--include-legacy', $argv, true)) $roots[] = dirname(__DIR__) . '/uploads';
foreach ($roots as $root) {
    if (!is_dir($root)) continue;
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $entry) {
        if (!$entry->isFile() || $entry->isLink() || $entry->getMTime() >= $cutoff) continue;
        $path = 'uploads/' . substr($entry->getPathname(), strlen($root) + 1);
        if (!private_media_path($path)) continue;
        $count++;
        if ($apply) {
            if (!unlink($entry->getPathname())) throw new RuntimeException('Cannot delete expired media');
            foreach (['children' => 'photo_path', 'daily_stories' => 'photo_path'] as $table => $col) {
                $pdo->prepare("UPDATE {$table} SET {$col} = NULL WHERE {$col} = ?")->execute([$path]);
            }
            $pdo->prepare('DELETE FROM drawings WHERE image_path = ?')->execute([$path]);
        }
    }
}
// Bound authentication/reset metadata growth without storing IPs or raw tokens in logs.
if ($apply) {
    $pdo->prepare('DELETE FROM auth_attempts WHERE window_start < ?')->execute([time() - 86400]);
    $pdo->prepare('DELETE FROM password_resets WHERE expires_at < ?')->execute([date('Y-m-d H:i:s', time() - 86400)]);
}
echo ($apply ? 'Deleted ' : 'Would delete ') . $count . " expired media files.\n";
