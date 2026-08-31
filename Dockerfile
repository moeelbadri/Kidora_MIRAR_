FROM php:8.3-apache

RUN docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite headers \
    && printf 'upload_max_filesize=16M\npost_max_size=16M\nmemory_limit=256M\n' \
        > /usr/local/etc/php/conf.d/kidora.ini

WORKDIR /var/www/html
COPY . /var/www/html

RUN mkdir -p \
        uploads/photos \
        assets/images/characters \
        assets/audio/characters \
        storage \
    && chown -R www-data:www-data \
        uploads assets/images/characters assets/audio/characters storage \
    && chmod +x /var/www/html/docker-entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/var/www/html/docker-entrypoint.sh"]
