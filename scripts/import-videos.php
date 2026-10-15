<?php
/** CLI-only inventory / import of authorized YouTube copies. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$options = getopt('', ['download', 'rights-confirmed', 'include-shortlist', 'cookies:']);
$download = isset($options['download']);
if ($download && !isset($options['rights-confirmed'])) {
    fwrite(STDERR, "Import only videos you own or have permission to host. Add --rights-confirmed to proceed.\n");
    exit(1);
}

$cookies = trim((string)($options['cookies'] ?? getenv('YT_DLP_COOKIES') ?: ''));
if ($cookies !== '' && !is_readable($cookies)) {
    fwrite(STDERR, "Cookie file not readable: {$cookies}\n");
    exit(1);
}

function kidora_import_ytdlp_binary(): string {
    $env = trim((string)getenv('YT_DLP'));
    if ($env !== '' && is_executable($env)) {
        return $env;
    }
    $home = trim((string)getenv('HOME'));
    if ($home !== '') {
        $local = $home . '/.local/bin/yt-dlp';
        if (is_executable($local)) {
            return $local;
        }
    }
    return 'yt-dlp';
}

/** @return list<string> */
function kidora_import_ids_from_shortlist(string $path): array {
    if (!is_readable($path)) {
        return [];
    }
    $text = file_get_contents($path);
    if ($text === false) {
        return [];
    }
    preg_match_all('/`([A-Za-z0-9_-]{11})`/', $text, $m);
    return array_values(array_unique($m[1] ?? []));
}

$ids = [KIDORA_SHOWCASE_VIDEO_ID, KIDORA_SAFETY_VIDEO_ID];
$friendVideos = require __DIR__ . '/../includes/friend-videos.php';
foreach ($friendVideos as $videos) {
    foreach ($videos as $video) $ids[] = $video['id'];
}
foreach (['tasks', 'history_figures', 'safety_content'] as $table) {
    foreach ($pdo->query("SELECT DISTINCT youtube_id FROM {$table} WHERE youtube_id IS NOT NULL AND youtube_id <> ''") as $row) {
        $id = youtube_id_from_input($row['youtube_id']);
        if ($id === null) {
            fwrite(STDERR, "Invalid video reference in {$table}: {$row['youtube_id']}\n");
            exit(1);
        }
        $ids[] = $id;
    }
}

if (isset($options['include-shortlist'])) {
    $shortlist = __DIR__ . '/../docs/mission-video-shortlist.md';
    foreach (kidora_import_ids_from_shortlist($shortlist) as $id) {
        $ids[] = $id;
    }
}

$directory = __DIR__ . '/../assets/videos';
if ($download && !is_dir($directory) && !mkdir($directory, 0755, true)) {
    fwrite(STDERR, "Cannot create assets/videos.\n");
    exit(1);
}

$ytdlp = kidora_import_ytdlp_binary();
$missing = 0;
foreach (array_unique($ids) as $id) {
    if (local_video_url($id) !== null) {
        echo "READY   {$id}\n";
        continue;
    }
    if (!$download) {
        echo "MISSING assets/videos/{$id}.mp4\n";
        $missing++;
        continue;
    }
    // Temporary files are not visible to players; publish only a completed MP4.
    // H.264 + AAC at up to 720p keeps compatibility with Safari and mobile devices.
    $temporary = $directory . '/.' . $id . '-import';
    $command = [
        $ytdlp, '--no-update', '--no-playlist', '--no-overwrites', '--js-runtimes', 'node',
        '--extractor-args', 'youtube:player_client=android,web',
        '-f', 'bv[ext=mp4][vcodec^=avc1][height<=720]+ba[ext=m4a]/b[ext=mp4][vcodec^=avc1][height<=720]',
        '--merge-output-format', 'mp4', '--remux-video', 'mp4',
        '--postprocessor-args', 'ffmpeg:-movflags +faststart',
        '-o', $temporary . '.%(ext)s', 'https://www.youtube.com/watch?v=' . $id,
    ];
    if ($cookies !== '') {
        array_splice($command, 1, 0, ['--cookies', $cookies]);
    }
    echo "IMPORT  {$id}\n";
    $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes);
    $status = is_resource($process) ? proc_close($process) : 1;
    $file = $temporary . '.mp4';
    if ($status !== 0 || !is_file($file) || filesize($file) === 0 || !rename($file, $directory . '/' . $id . '.mp4')) {
        @unlink($file);
        fwrite(STDERR, "FAILED {$id}. Copy an authorized MP4 to assets/videos/{$id}.mp4");
        if ($cookies === '') {
            fwrite(STDERR, ", or pass --cookies=netscape.txt / YT_DLP_COOKIES if YouTube blocks this host.\n");
        } else {
            fwrite(STDERR, ".\n");
        }
        $missing++;
    } else {
        echo "READY   {$id}\n";
    }
}
if ($missing) {
    fwrite(STDERR, "{$missing} video(s) still missing. See docs/local-videos.md.\n");
}
exit($missing ? 1 : 0);
