#!/bin/sh
set -eu

# Volume mounts arrive as root-owned; Apache writes uploads as www-data.
for dir in \
    /var/www/html/uploads \
    /var/www/html/assets/images/characters \
    /var/www/html/assets/audio/characters \
    /var/www/html/storage
do
    mkdir -p "$dir"
    chown -R www-data:www-data "$dir"
done

exec apache2-foreground
