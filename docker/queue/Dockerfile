FROM php:8.4-cli-alpine

RUN apk add --no-cache \
    supervisor \
    git \
    unzip \
    postgresql-dev \
    icu-dev \
    oniguruma-dev \
    libzip-dev \
    autoconf g++ make linux-headers

RUN docker-php-ext-install pdo pdo_pgsql intl zip mbstring bcmath pcntl sockets

RUN pecl install redis && docker-php-ext-enable redis

WORKDIR /var/www/html

COPY backend /var/www/html

COPY docker/supervisor/exchange-workers.conf /etc/supervisor/conf.d/exchange-workers.conf
COPY docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

USER root
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
