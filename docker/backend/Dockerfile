FROM php:8.4-cli-alpine

RUN apk add --no-cache \
    git curl bash supervisor \
    postgresql-dev icu-dev oniguruma-dev libzip-dev \
    autoconf g++ make linux-headers openssl-dev \
    brotli-dev zstd-dev

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN pecl install swoole redis \
    && docker-php-ext-enable swoole redis \
    && docker-php-ext-install \
        pdo pdo_pgsql intl zip mbstring bcmath pcntl sockets

RUN { \
        echo 'memory_limit=512M'; \
        echo 'upload_max_filesize=50M'; \
        echo 'post_max_size=50M'; \
        echo 'max_execution_time=300'; \
        echo 'date.timezone=UTC'; \
    } > /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/html

COPY backend /var/www/html
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

RUN chmod -R 775 storage bootstrap/cache

RUN mkdir -p /etc/supervisor/conf.d

COPY docker/backend/backend-workers.conf /etc/supervisor/conf.d/exchange-supervisord.conf
COPY docker/backend/backend-supervisord.conf /etc/supervisor/supervisord.conf

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8000 8080

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]