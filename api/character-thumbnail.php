<?php
// This endpoint serves public character artwork, never user photos or arbitrary files.
$root = realpath(__DIR__ . '/../assets/images/characters');
$path = is_string($_GET['path'] ?? null) ? $_GET['path'] : '';
$width = filter_var($_GET['w'] ?? 320, FILTER_VALIDATE_INT);
$file = $root && !str_contains($path, "\0") ? realpath($root . '/' . $path) : false;
if (!$file || !str_starts_with($file, $root . '/') || !is_file($file)
    || !in_array($width, [320, 640], true)) {
    http_response_code(404);
    exit;
}
$info = @getimagesize($file);
if (!$info || !in_array($info['mime'], ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true)
    || $info[0] * $info[1] > 16000000) {
    http_response_code(415);
    exit;
}
$key = hash('sha256', $file . ':' . filemtime($file) . ':' . filesize($file) . ':' . $width);
$directory = __DIR__ . '/../storage/character-thumbnails';
$cached = $directory . '/' . $key . '.webp';
if (!is_file($cached)) {
    if (!is_dir($directory)) @mkdir($directory, 0755, true);
    $source = imagecreatefromstring(file_get_contents($file));
    if (!$source) { http_response_code(415); exit; }
    $targetWidth = min($width, $info[0]);
    $targetHeight = max(1, (int)round($info[1] * $targetWidth / $info[0]));
    $image = imagecreatetruecolor($targetWidth, $targetHeight);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagecopyresampled($image, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $info[0], $info[1]);
    $temporary = tempnam($directory, '.thumbnail-');
    $saved = $temporary && imagewebp($image, $temporary, 78) && rename($temporary, $cached);
    imagedestroy($image);
    imagedestroy($source);
    if (!$saved) {
        if ($temporary && is_file($temporary)) unlink($temporary);
        http_response_code(503);
        exit;
    }
}
header('Content-Type: image/webp');
header('Cache-Control: public, max-age=86400');
header('ETag: "' . $key . '"');
header('X-Content-Type-Options: nosniff');
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === '"' . $key . '"') {
    http_response_code(304);
    exit;
}
header('Content-Length: ' . filesize($cached));
readfile($cached);
