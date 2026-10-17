# Locally hosted videos

All former YouTube players now use locally hosted HTML5 video with Google's
Shaka Player UI (5.2.13, bundled locally under Apache-2.0). There is no YouTube
iframe, player API, or remote playback fallback. Existing `youtube_id` database
columns remain source references, so no database migration is needed.

## Supply the files

Only host videos you own or have permission to download and redistribute.
Prefer original MP4 files from the creator, or an export of your own uploads.
Use MP4 with H.264 video and AAC audio for mobile/Safari compatibility.

Copy each authorized file to `assets/videos/VIDEO_ID.mp4`. The video ID is the
11-character source ID already stored in the task, figure, or safety record.
The landing-page and fallback safety IDs are configured in `config/config.php`.
The Friends' Stories catalog is in `includes/friend-videos.php` and is included
in the import inventory too.
Admin task/figure forms still accept source URLs or IDs, but saving a reference
does **not** download it. Admin lists show whether its local file is present.

### Upload from the admin panel

Open **Admin → Videos (الفيديوهات)**. Enter the same source URL/11-character ID
used by the task or figure, choose the file, confirm your publishing rights,
and upload. For an original video without a source ID, choose a unique
11-character ID using ASCII letters, digits, `-`, or `_`, then assign that ID
to the task/figure. Uploading does not change database references automatically.

Uploads accept MP4 with one H.264/yuv420p video stream and AAC audio (or no
audio), up to **90 MiB**. The server checks actual codecs with `ffprobe`; it
does not transcode the original. The Docker image includes ffprobe, a 90M PHP upload limit,
and a 96M POST limit. Other hosting setups need those settings and the
`ffprobe` and `timeout` executables too. Check proxy limits if uploads fail
before reaching PHP; 90 MiB stays below a 100 MB request cap with form overhead.

The library provides inline previews. To replace a video, choose its Replace
link and explicitly tick the replacement checkbox. Validation completes before
the new file atomically replaces the old one; rejected uploads leave the
existing file unchanged. Player URLs include a version so replacements do not
reuse the previous browser/CDN URL. Only authenticated admins with a valid
session CSRF token can upload. Change the default admin credentials before
exposing uploads on a public installation.

### Mobile landing-page delivery

The landing page renders without an intro overlay or downloading Shaka. Lesson
and library-preview players download Google's player and its styles on demand.
The hero shows a small WebP poster first, then plays a muted video after the
page loads and the video is visible. Save-Data, slow 2G connections, and reduced
motion keep it poster-only until the visitor presses Play.

Container startup and replacement of the configured showcase video generate
a poster and a 480px-wide H.264/AAC, faststart mobile copy in the background.
These versioned derivatives live in `assets/videos/derived/` on the same host
mount; originals are never modified or committed. Desktop uses the original.
Until generation finishes (or if it fails), playback falls back to the original.
To retry manually: `php scripts/prepare-landing-video.php`.

Landing character images use cached 320px WebP thumbnails and are requested
only near the viewport, including the registration picker. Originals stay
unchanged. Thumbnail files are disposable under `storage/character-thumbnails/`;
non-Docker installations need PHP GD with WebP support and writable storage.

Apache caches public media, gives versioned derivatives immutable URLs, and
revalidates CSS/JS so code changes are not held in a stale browser cache.
PHP/HTML stays private. A CDN/proxy must honor these origin headers rather than
overriding all assets with `no-store`; verify public responses after deployment.

Checks: build the Docker image, then run `bash scripts/test-landing-media.sh IMAGE`
and `bash scripts/test-video-upload.sh IMAGE`. Both use disposable containers.

```bash
# Report every referenced file that needs importing (exit 1 if any are missing).
php scripts/import-videos.php

# Optional download for videos you are authorized to host.
# Requires a recent yt-dlp, Node.js, and ffmpeg on your workstation, not the web server.
php scripts/import-videos.php --download --rights-confirmed

# Same, with a convenience wrapper (honours YT_DLP_COOKIES if set):
chmod +x scripts/download-yt-videos.sh
./scripts/download-yt-videos.sh

# Also fetch every ID listed in docs/mission-video-shortlist.md (mission picks, not all seeded yet):
php scripts/import-videos.php --download --rights-confirmed --include-shortlist

# When YouTube returns «Sign in to confirm you’re not a bot», export Netscape cookies
# from a logged-in browser session (never commit the file) and retry:
export YT_DLP_COOKIES=/path/to/youtube-cookies.txt
# Or place Netscape cookies at repo root (gitignored): ./cookies.txt
php scripts/import-videos.php --download --rights-confirmed --cookies="$YT_DLP_COOKIES"
```

The importer deduplicates IDs, skips existing nonempty files, limits downloads
to 720p H.264/AAC, and publishes only completed MP4s. Failed imports return a
nonzero status; they never fall back to YouTube embeds. Interrupted downloads
use hidden temporary files and can be resumed. Only run one importer at a time.

YouTube may block automated downloads with a sign-in/bot check. In that case,
obtain the original files from the owner or run yt-dlp in your own authorized,
authenticated environment. Do not share browser cookies in source control or
chat. The local library currently has 13 MP4 files, kept outside Git and Docker
images. The Spider-Man reference
`B4OELaY0ink` still has no local file and displays the unavailable message.

## Dokploy: persistent host folder (bind mount)

Video files are ignored by Git and excluded from the Docker build context.
The image contains only an empty `assets/videos/` mount point. No player or
database change is needed: Apache serves the mounted files at the existing
`/assets/videos/VIDEO_ID.mp4` URLs.

Use `/opt/kidora/videos` as an example host path; it must be on the **server
running the application container**, outside Dokploy's temporary build/checkout
directory. It is not a folder inside the image or on your workstation.

1. On that server, create the persistent directory:
   ```bash
   sudo mkdir -p /opt/kidora/videos
   sudo chmod 755 /opt/kidora /opt/kidora/videos
   ```
2. From your workstation, copy the authorized library to the server (replace
   `USER@HOST` with your SSH destination; that user needs write access to the
   destination, or upload to a staging folder and move the files with sudo):
   ```bash
   rsync -av --include='*.mp4' --exclude='*' assets/videos/ USER@HOST:/opt/kidora/videos/
   ```
   On the server, make files readable by Apache:
   ```bash
   sudo find /opt/kidora/videos -type f -name '*.mp4' -exec chmod 644 {} +
   ```
3. In the Dokploy application's volume/mount settings, add a **bind mount**:

   | Setting | Value |
   | --- | --- |
   | Host/source path | `/opt/kidora/videos` |
   | Container/target path | `/var/www/html/assets/videos` |
   | Read only | No (admin uploads need write access) |

   The entrypoint gives `www-data` ownership of the mounted video directory,
   just like the existing upload directories. Dokploy v0.30.8 application
   bind mounts are already writable. Do not mark this mount read-only if you
   want to upload from the admin panel.

   Equivalent Docker option:
   ```bash
   --mount type=bind,source=/opt/kidora/videos,target=/var/www/html/assets/videos
   ```
   For a Compose-based Dokploy application, add this under the PHP service:
   ```yaml
   volumes:
     - type: bind
       source: /opt/kidora/videos
       target: /var/www/html/assets/videos
       read_only: false
       bind:
         create_host_path: false
   ```
   Do not use a new empty named volume: it will not contain the host's files.
4. Redeploy the application after saving the mount. Verify a known file:
   ```bash
   curl -I https://YOUR_DOMAIN/assets/videos/34WNhf4wris.mp4
   curl -sS -D - -o /dev/null -H 'Range: bytes=0-1023' \
     https://YOUR_DOMAIN/assets/videos/34WNhf4wris.mp4
   ```
   Expect `Content-Type: video/mp4`, then `206 Partial Content` and a
   `Content-Range` header for the range request. Check playback and seeking in
   the browser too. Missing/empty/unreadable files remain unavailable to players.

The mount is writable for authenticated admin uploads, but child playback never
downloads or writes media. You can also import on your workstation and copy
files to the host; no image rebuild is required for library updates. Back up the
host folder separately from Git and the application database. If replicas run
on multiple servers, each needs the same media at that path (or shared storage).

Removing tracked videos from a new commit does **not** remove their bytes from
older Git commits or shrink existing clones. Reclaiming that space requires a
separately coordinated history rewrite; do not force-push as part of deployment.

## Playback behavior

The landing-page video loops muted without controls to keep the hero clean.
Lesson and admin preview videos use Shaka's Arabic controls for playback,
seeking, volume, speed, and fullscreen; admin previews load only when selected.
No external player CDN, Cast SDK, or remote video fallback is used.
Lesson videos retain a play button if audible autoplay is blocked. Historical-figure
and safety videos advance when playback ends or the child chooses Next. Missing
or unplayable lesson files show an Arabic message and keep Next available;
tasks without video references continue to work without a player.
