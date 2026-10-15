# Locally hosted videos

All former YouTube players now use native HTML5 video. There is no YouTube
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
   On the server, make files readable by Apache without granting write access:
   ```bash
   sudo find /opt/kidora/videos -type f -name '*.mp4' -exec chmod 644 {} +
   ```
3. In the Dokploy application's volume/mount settings, add a **bind mount**:

   | Setting | Value |
   | --- | --- |
   | Host/source path | `/opt/kidora/videos` |
   | Container/target path | `/var/www/html/assets/videos` |
   | Read only | Yes, if supported by your Dokploy version |

   Dokploy v0.30.8 application mounts do not expose a read-only flag. For that
   version, keep the folder root-owned and files mode 644 (directory mode 755),
   so Apache/PHP running as `www-data` cannot modify them. This is filesystem
   permission protection, not a Docker read-only mount. Compose and plain
   Docker deployments can enforce the read-only mount shown below.

   Equivalent Docker option:
   ```bash
   --mount type=bind,source=/opt/kidora/videos,target=/var/www/html/assets/videos,readonly
   ```
   For a Compose-based Dokploy application, add this under the PHP service:
   ```yaml
   volumes:
     - type: bind
       source: /opt/kidora/videos
       target: /var/www/html/assets/videos
       read_only: true
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

Keep the mount read-only: PHP never downloads or writes videos during web
requests. Import on your workstation and copy new files to the host using the
same process; no image rebuild is required for library updates. Back up the
host folder separately from Git and the application database. If replicas run
on multiple servers, each needs the same media at that path (or shared storage).

Removing tracked videos from a new commit does **not** remove their bytes from
older Git commits or shrink existing clones. Reclaiming that space requires a
separately coordinated history rewrite; do not force-push as part of deployment.

## Playback behavior

The landing-page video starts muted to satisfy browser autoplay rules. Lesson
videos retain native controls if audible autoplay is blocked. Historical-figure
and safety videos advance when playback ends or the child chooses Next. Missing
or unplayable lesson files show an Arabic message and keep Next available;
tasks without video references continue to work without a player.
