FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends ffmpeg \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite headers \
    && printf 'upload_max_filesize=90M\npost_max_size=96M\nmemory_limit=256M\nmax_input_time=300\n' \
        > /usr/local/etc/php/conf.d/kidora.ini

WORKDIR /var/www/html
COPY . /var/www/html

RUN mkdir -p \
        uploads/photos \
        uploads/drawings \
        assets/videos \
        assets/images/characters \
        assets/audio/characters \
        storage \
    && chown -R www-data:www-data \
        uploads assets/videos assets/images/characters assets/audio/characters storage \
    && chmod +x /var/www/html/docker-entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/var/www/html/docker-entrypoint.sh"]
