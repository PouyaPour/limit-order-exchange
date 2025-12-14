FROM php:8.4-cli-alpine

RUN apk add --no-cache \
    git curl bash \
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

COPY . .
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

RUN chmod -R 775 storage bootstrap/cache

EXPOSE 8000

CMD ["php", "artisan", "octane:start", "--server=swoole", "--host=0.0.0.0", "--port=8000", "--workers=4", "--task-workers=6", "--max-requests=500"]