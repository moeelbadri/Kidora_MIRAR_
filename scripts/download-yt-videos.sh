#!/usr/bin/env bash
# Download every referenced YouTube ID into assets/videos/{ID}.mp4 via yt-dlp.
# Run from your machine (or any host) where YouTube allows yt-dlp — not necessarily production.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

ARGS=(php scripts/import-videos.php --download --rights-confirmed)
if [[ -n "${YT_DLP_COOKIES:-}" && -f "${YT_DLP_COOKIES}" ]]; then
  ARGS+=(--cookies="${YT_DLP_COOKIES}")
fi
exec "${ARGS[@]}" "$@"
