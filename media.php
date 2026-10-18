<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) { http_response_code(405); exit; }
$childId = (int)($_SESSION['child_id'] ?? 0);
// The remember cookie permits only its owner's media on the continue-login screen.
if (!$childId) $childId = (int)(kidora_peek_remember($pdo)['id'] ?? 0);
if (!$childId && empty($_SESSION['is_admin'])) { http_response_code(401); exit; }
$path = is_string($_GET['path'] ?? null) ? $_GET['path'] : '';
$file = private_media_path($path);
if (!$file) { http_response_code(404); exit; }
$owned = false;
foreach (['children' => 'photo_path', 'daily_stories' => 'photo_path', 'drawings' => 'image_path'] as $table => $column) {
    $ownerColumn = $table === 'children' ? 'id' : 'child_id';
    $sql = "SELECT 1 FROM {$table} WHERE {$column} = ?";
    $params = [$path];
    if (empty($_SESSION['is_admin'])) { $sql .= " AND {$ownerColumn} = ?"; $params[] = $childId; }
    $st = $pdo->prepare($sql . ' LIMIT 1');
    $st->execute($params);
    if ($st->fetchColumn()) { $owned = true; break; }
}
if (!$owned || filemtime($file) < time() - KIDORA_MEDIA_RETENTION_DAYS * 86400) { http_response_code(404); exit; }
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file);
if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) { http_response_code(404); exit; }
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file));
header('Content-Disposition: inline; filename="' . basename($path) . '"');
session_write_close();
if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') readfile($file);
