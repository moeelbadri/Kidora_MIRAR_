# Kidora security deployment — 18 October 2026

The selected security, privacy, migration, concurrency, asset-loading and test
improvements are running on `https://kidora.studio` and `https://kidoora.anivia.site`.
The reset-link canonical URL is `https://kidora.studio`. Admin credentials and the
application timezone were intentionally not changed.

## Runtime configuration

- Dokploy application: `HGeZiLR08cCzntcCIJskb`; Swarm service: `kidoora-laravel-y4qzka`.
- Initial manually deployed image: `kidora-security-release:20261017`.
  Subsequent Git-backed Dokploy builds use the application image name.
- Persistent volume `kidora-private` mounts at `/var/www/kidora-private`.
- `KIDORA_PRIVATE_DIR=/var/www/kidora-private`, `KIDORA_APP_URL=https://kidora.studio`,
  `KIDORA_TRUSTED_PROXIES=10.0.1.2`, `KIDORA_MEDIA_RETENTION_DAYS=365` are saved
  in both the service and Dokploy application configuration. Recheck the exact
  trusted proxy address if Traefik's network attachment changes.
- The existing MySQL connection credentials remain unchanged. The existing
  database user still has database-level ALL privileges; the deployment did not
  create a separate CRUD-only runtime account.
- `/etc/cron.d/kidora-media-retention` runs the private-media cleanup daily at
  03:27 server time, with a non-overlap lock and the current application container
  selected by its Swarm service label. `/usr/local/sbin/kidora-prune-media` without
  arguments is a safe dry run. The scheduled job passes `--apply`.
- Original public-upload files are retained, but HTTP access to `/uploads` is
  blocked. Cleanup does not include legacy originals or backups.

## Backups and validation

Root-only backups are in `/root/kidora-backups/20261018T000109Z`: initial and final
MySQL dumps, original service/Dokploy configuration, upload snapshots, character
media, the previous source tree, and the exact new release source archive
`security-release-source.tar.gz`. These backups contain private data and secrets;
never publish them. The prior image is tagged `kidora-rollback:20261018t000109z`.

The production dump was restored in isolated MySQL and migrated twice before the
live migration. All 21 existing table counts were preserved in rehearsal and
production, including 19 children, 11 daily stories, 8 drawings and 19 subscriptions.
All 25 legacy media files were verified byte-for-byte after private copying.
Integration, video-upload and mobile-media/thumbnail regression suites passed.
Live HTTPS checks covered secure cookies, CSRF rejection, internal-path denial,
anonymous private-media denial and rendered Arabic privacy/registration screens.

## Git publication and future deployments

Automatic Git deployment was paused during the initial manual rollout to prevent
older Git code from overwriting the security release. This reconciled release is
prepared against GitHub main, preserving its newer mobile-video and thumbnail
changes. Automatic deployment may be re-enabled when publishing this release.
Do not deploy a pre-security Git revision: it could expose legacy uploads again.

The original workspace's local `main` predates those live-site changes and still
contains uncommitted work. The reconciled publication checkout is
`/root/download/Kidora_MIRAR_/.amp/in/security-publish`; do not overwrite GitHub main
from the older workspace tree. The exact initial release source is also retained
in the root-only source archive above.

Future deployments must retain the private-media volume and saved environment.
Migrations remain an explicit offline CLI step, not a web-request/startup action.
An image rollback alone does not restore the pre-migration database or prior token
state; restore data only in a separately reviewed maintenance window if needed.
