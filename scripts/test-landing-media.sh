#!/usr/bin/env bash
# Public HTTP contracts on a disposable real Apache/PHP stack, not production media.
set -euo pipefail
image=${1:-kidora-mobile-check}
tmp=$(mktemp -d)
cid=''
cleanup() { if [ -n "$cid" ]; then docker stop "$cid" >/dev/null; fi; rm -rf "$tmp"; }
trap cleanup EXIT
cid=$(docker run -d --rm -p 127.0.0.1::80 "$image")
base="http://$(docker port "$cid" 80/tcp)"
for attempt in {1..30}; do
    if curl -fsS "$base/" -o /dev/null 2>/dev/null; then break; fi
    sleep 1
done
docker exec "$cid" php -r '
    $im=imagecreatetruecolor(640,320); imagealphablending($im,false); imagesavealpha($im,true);
    imagefill($im,0,0,imagecolorallocatealpha($im,0,0,0,127));
    imagefilledrectangle($im,320,0,639,319,imagecolorallocatealpha($im,0,0,255,0));
    imagepng($im,"assets/images/characters/thumbnail-test.png");'
url="$base/api/character-thumbnail.php?path=thumbnail-test.png&w=320"
curl -fsS -D "$tmp/headers" "$url" -o "$tmp/thumb.webp"
grep -qi 'Content-Type: image/webp' "$tmp/headers"
grep -qi 'Cache-Control: public' "$tmp/headers"
! grep -qi 'Set-Cookie:' "$tmp/headers"
etag=$(sed -n 's/^[Ee][Tt][Aa][Gg]: //p' "$tmp/headers" | tr -d '\r')
test "$(curl -sS -o /dev/null -w '%{http_code}' -H "If-None-Match: $etag" "$url")" = 304
docker cp "$tmp/thumb.webp" "$cid":/tmp/thumb.webp
docker exec "$cid" php -r '
    $im=imagecreatefromwebp("/tmp/thumb.webp");
    if(imagesx($im)!==320 || imagesy($im)!==160 || ((imagecolorat($im,10,10)>>24)&127)<100
        || (imagecolorat($im,300,100)&255)<200) exit(1);'
for query in 'path=../../../config/config.php&w=320' 'path=thumbnail-test.png&w=999' 'path[]=x&w=320' 'path=missing.png&w=320'; do
    test "$(curl -g -sS -o /dev/null -w '%{http_code}' "$base/api/character-thumbnail.php?$query")" = 404
done
docker exec "$cid" php -r 'touch("assets/images/characters/thumbnail-test.png",time()+2);'
curl -fsS -D "$tmp/replaced" "$url" -o /dev/null
! grep -qF "ETag: $etag" "$tmp/replaced"
curl -fsSI "$base/assets/css/main.css" | grep -qi 'Cache-Control: public, max-age=0, must-revalidate'
curl -fsSI "$base/" | grep -qi 'Cache-Control: no-store'

docker exec "$cid" ffmpeg -v error -f lavfi -i testsrc2=s=720x360:d=2 -c:v libx264 -pix_fmt yuv420p assets/videos/9yJF1CReRCQ.mp4
docker exec "$cid" php scripts/prepare-landing-video.php
curl -fsS "$base/" -o "$tmp/page"
mobile=$(grep -o 'data-mobile-src="[^"]*"' "$tmp/page" | cut -d'"' -f2)
poster=$(grep -o 'poster="[^"]*"' "$tmp/page" | cut -d'"' -f2)
test -n "$mobile" && test -n "$poster"
curl -fsS "$base$mobile" -o "$tmp/mobile.mp4"
curl -fsSI "$base$poster" | grep -qi 'Cache-Control: public, max-age=31536000, immutable'
test "$(curl -sS -H 'Range: bytes=0-31' -o /dev/null -w '%{http_code}' "$base$mobile")" = 206
docker cp "$tmp/mobile.mp4" "$cid":/tmp/mobile.mp4
docker exec "$cid" php -r '$f=file_get_contents("/tmp/mobile.mp4"); if(strpos($f,"moov")>strpos($f,"mdat"))exit(1);'
docker exec "$cid" ffprobe -v error -select_streams v:0 -show_entries stream=codec_name,width,height -of csv=p=0 /tmp/mobile.mp4 | grep -qx 'h264,480,240'
docker exec "$cid" php -r 'touch("assets/videos/9yJF1CReRCQ.mp4",time()+2);'
docker exec "$cid" php scripts/prepare-landing-video.php
curl -fsS "$base/" -o "$tmp/replaced-page"
replacement=$(grep -o 'data-mobile-src="[^"]*"' "$tmp/replaced-page" | cut -d'"' -f2)
test "$replacement" != "$mobile"
curl -fsS "$base$replacement" -o /dev/null

# Authenticated lesson/task responses must expose local files, with no YouTube player dependency.
docker exec -u www-data "$cid" php -r '
    session_id("local-media-regression"); require "/var/www/html/config/db.php";
    $character=$pdo->query("SELECT MIN(id) FROM characters")->fetchColumn();
    $pdo->prepare("INSERT INTO children(name,email,password,age,character_1,trial_ends_at) VALUES(?,?,?,?,?,?)")
        ->execute(["Video Test","media-test@example.invalid","unused",8,$character,"2099-01-01"]);
    $_SESSION["child_id"]=(int)$pdo->lastInsertId();
    foreach(["tasks","safety_content"] as $table) $pdo->prepare("UPDATE $table SET youtube_id=?")->execute(["9yJF1CReRCQ"]);
    session_write_close();'
for page in tasks safety; do
    curl -fsS -b PHPSESSID=local-media-regression "$base/$page.php" -o "$tmp/$page"
    grep -q '9yJF1CReRCQ.mp4' "$tmp/$page"
    ! grep -Eq 'iframe_api|YT\.Player|youtube(-nocookie)?\.com/embed' "$tmp/$page"
done
echo 'PASS: WebP size/alpha/aspect ratio, cache revalidation/invalidation, path confinement, private HTML, mobile H.264/faststart, poster cache, and Range requests.'
