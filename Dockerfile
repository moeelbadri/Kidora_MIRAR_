FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends ffmpeg libpng-dev libjpeg62-turbo-dev libwebp-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_mysql gd \
    && a2enmod rewrite headers \
    && printf 'upload_max_filesize=90M\npost_max_size=96M\nmemory_limit=256M\nmax_input_time=300\n' \
        > /usr/local/etc/php/conf.d/kidora.ini

WORKDIR /var/www/html
COPY . /var/www/html
ENV KIDORA_PRIVATE_DIR=/var/www/kidora-private
COPY config/static-assets.conf /etc/apache2/conf-enabled/kidora-static-assets.conf

RUN mkdir -p \
        uploads/photos \
        uploads/drawings \
        assets/videos \
        assets/images/characters \
        assets/audio/characters \
        storage /var/www/kidora-private \
    && chown -R www-data:www-data \
        uploads assets/videos assets/images/characters assets/audio/characters storage /var/www/kidora-private \
    && chmod +x /var/www/html/docker-entrypoint.sh

# .htaccess is required: internal directories and legacy uploads must not be served.
RUN printf '<Directory /var/www/html>\nAllowOverride All\nOptions -Indexes\n</Directory>\n' \
    > /etc/apache2/conf-available/kidora.conf && a2enconf kidora

EXPOSE 80
ENTRYPOINT ["/var/www/html/docker-entrypoint.sh"]
