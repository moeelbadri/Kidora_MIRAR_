#!/bin/sh
set -eu

# Volume mounts arrive as root-owned; Apache writes uploads as www-data.
for dir in \
    /var/www/html/uploads \
    /var/www/html/assets/videos \
    /var/www/html/assets/images/characters \
    /var/www/html/assets/audio/characters \
    /var/www/html/storage
do
    mkdir -p "$dir"
    chown -R www-data:www-data "$dir"
done

# Keep startup responsive; generate only missing poster/mobile versions in the background.
su -s /bin/sh www-data -c 'php /var/www/html/scripts/prepare-landing-video.php' &

exec apache2-foreground
