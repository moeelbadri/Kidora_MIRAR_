#!/usr/bin/env bash
# Integration contract: disposable Apache/PHP container, real multipart uploads.
# First build: docker build -t kidora-video-uploader-check .
set -euo pipefail
image=${1:-kidora-video-uploader-check}
tmp=$(mktemp -d)
cid=''
cleanup() {
    if [ -n "$cid" ]; then docker stop "$cid" >/dev/null; fi
    rm -rf "$tmp"
}
trap cleanup EXIT
mkdir "$tmp/media"
cid=$(docker run -d --rm -p 127.0.0.1::80 --mount "type=bind,source=$tmp/media,target=/var/www/html/assets/videos" "$image")
docker exec --user www-data "$cid" php scripts/migrate.php
base="http://$(docker port "$cid" 80/tcp)"
for attempt in {1..30}; do
    if curl -fsS "$base/admin/login.php" -o /dev/null 2>/dev/null; then break; fi
    sleep 1
done
docker exec "$cid" ffmpeg -v error -f lavfi -i color=c=blue:s=160x90:d=1 -f lavfi -i sine=frequency=440:duration=1 -c:v libx264 -pix_fmt yuv420p -c:a aac -shortest /tmp/valid.mp4
docker exec "$cid" ffmpeg -v error -f lavfi -i color=c=red:s=160x90:d=1 -c:v libx264 -pix_fmt yuv420p /tmp/replacement.mp4
docker exec "$cid" ffmpeg -v error -f lavfi -i color=c=green:s=160x90:d=1 -c:v mpeg4 /tmp/wrong-codec.mp4
docker exec "$cid" ffmpeg -v error -i /tmp/valid.mp4 -c:v copy -c:a libmp3lame /tmp/wrong-audio.mp4
for name in valid replacement wrong-codec wrong-audio; do docker cp "$cid:/tmp/$name.mp4" "$tmp/$name.mp4" >/dev/null; done
# A real MP4 padded to the exact boundary also catches the former 16M PHP cap.
truncate -s 94371840 "$tmp/valid.mp4"
printf '<?php echo "not video"; ?>' > "$tmp/fake.mp4"
truncate -s 94371841 "$tmp/oversize.mp4"

code=$(curl -sS -o /dev/null -w '%{http_code}' -F video_id=UploadTest1 "$base/admin/upload-video.php")
test "$code" = 403
test ! -e "$tmp/media/UploadTest1.mp4"
curl -fsS -c "$tmp/cookies" "$base/admin/login.php" -o "$tmp/login"
login_token=$(python3 -c 'import re,sys; print(re.search(r"name=\"csrf_token\" value=\"([^\"]+)\"",sys.stdin.read())[1])' < "$tmp/login")
curl -fsS -b "$tmp/cookies" -c "$tmp/cookies" --data-urlencode "csrf_token=$login_token" -d 'email=admin%40kidora.com&password=admin123' "$base/admin/login.php" -o /dev/null
page=$(curl -fsS -b "$tmp/cookies" "$base/admin/index.php?tab=videos")
token=$(printf '%s' "$page" | python3 -c 'import re,sys; print(re.search(r"name=\"csrf_token\" value=\"([^\"]+)\"",sys.stdin.read())[1])')
upload() {
    curl -fsS -L -b "$tmp/cookies" -F "csrf_token=$token" -F rights_confirmed=1 -F video_id=UploadTest1 "$@" "$base/admin/upload-video.php"
}
upload -F "video=@$tmp/valid.mp4" > "$tmp/page"
grep -q 'تم حفظ الفيديو' "$tmp/page"
cmp "$tmp/valid.mp4" "$tmp/media/UploadTest1.mp4"
first_url=$(grep -o '/assets/videos/UploadTest1.mp4?v=[0-9]*' "$tmp/page" | head -1)
curl -fsS "$base$first_url" -o "$tmp/served.mp4"
cmp "$tmp/valid.mp4" "$tmp/served.mp4"

# Every rejected replacement must leave the original bytes intact.
upload -F "video=@$tmp/replacement.mp4" > "$tmp/page"
grep -q 'هذا المعرّف موجود' "$tmp/page"
for bad in fake wrong-codec wrong-audio oversize; do
    upload -F replace_existing=1 -F "video=@$tmp/$bad.mp4" > "$tmp/page"
    if grep -q 'تم حفظ الفيديو' "$tmp/page"; then echo "Incorrectly accepted $bad"; exit 1; fi
    cmp "$tmp/valid.mp4" "$tmp/media/UploadTest1.mp4"
done
code=$(curl -sS -o "$tmp/page" -w '%{http_code}' -b "$tmp/cookies" -F csrf_token=invalid -F replace_existing=1 -F "video=@$tmp/replacement.mp4" "$base/admin/upload-video.php")
test "$code" = 403
grep -q 'Invalid CSRF token' "$tmp/page"
upload -F rights_confirmed=0 -F replace_existing=1 -F "video=@$tmp/replacement.mp4" > "$tmp/page"
grep -q 'يجب تأكيد' "$tmp/page"
upload -F video_id=../escape -F "video=@$tmp/valid.mp4" > "$tmp/page"
grep -q '11 حرفاً' "$tmp/page"
cmp "$tmp/valid.mp4" "$tmp/media/UploadTest1.mp4"

upload -F replace_existing=1 -F "video=@$tmp/replacement.mp4" > "$tmp/page"
grep -q 'تم حفظ الفيديو' "$tmp/page"
cmp "$tmp/replacement.mp4" "$tmp/media/UploadTest1.mp4"
second_url=$(grep -o '/assets/videos/UploadTest1.mp4?v=[0-9]*' "$tmp/page" | head -1)
test "$first_url" != "$second_url"
curl -fsS "$base$second_url" -o "$tmp/served.mp4"
cmp "$tmp/replacement.mp4" "$tmp/served.mp4"
code=$(curl -sS -o /dev/null -w '%{http_code}' -H 'Range: bytes=0-31' "$base$second_url")
test "$code" = 206
docker restart "$cid" >/dev/null
base="http://$(docker port "$cid" 80/tcp)"
rm "$tmp/served.mp4"
for attempt in {1..30}; do
    if curl -fsS "$base$second_url" -o "$tmp/served.mp4" 2>/dev/null; then break; fi
    sleep 1
done
cmp "$tmp/replacement.mp4" "$tmp/served.mp4"
echo 'PASS: auth, CSRF, rights, ID, codec/content/size rejection, upload, explicit replacement, cache version, Range serving, and restart persistence.'
