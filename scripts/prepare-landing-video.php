<?php
// Derived media stays on the video volume, never in Git or the Docker image.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
$source = __DIR__ . '/../assets/videos/' . KIDORA_SHOWCASE_VIDEO_ID . '.mp4';
if (!is_file($source)) exit;
$directory = dirname($source) . '/derived';
if (!is_dir($directory) && !mkdir($directory, 0755, true)) exit(1);
$lock = fopen($directory . '/.prepare.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX)) exit(1);
clearstatcache(true, $source);
$version = filemtime($source);
$prefix = $directory . '/' . KIDORA_SHOWCASE_VIDEO_ID . '-' . $version;
$outputs = [
    '-poster.webp' => '-frames:v 1 -vf "scale=min(640\\,iw):-2" -c:v libwebp -quality 78 -f webp',
    '-mobile.mp4' => '-map 0:v:0 -map 0:a:0? -vf "scale=min(480\\,iw):-2" -c:v libx264 -preset fast -crf 28 -maxrate 350k -bufsize 700k -pix_fmt yuv420p -c:a aac -b:a 48k -movflags +faststart -f mp4',
];
foreach ($outputs as $suffix => $options) {
    $destination = $prefix . $suffix;
    if (is_file($destination)) continue;
    $temporary = $destination . '.' . bin2hex(random_bytes(4)) . '.tmp';
    exec('timeout 300s ffmpeg -nostdin -v error -threads 1 -protocol_whitelist file,pipe -i '
        . escapeshellarg($source) . ' -threads 1 ' . $options . ' ' . escapeshellarg($temporary), $output, $status);
    clearstatcache(true, $source);
    if ($status !== 0 || !is_file($temporary) || filesize($temporary) === 0 || filemtime($source) !== $version) {
        if (is_file($temporary)) unlink($temporary);
        exit(1);
    }
    chmod($temporary, 0644);
    rename($temporary, $destination);
}
