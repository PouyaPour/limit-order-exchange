FROM php:8.4-cli-alpine

# نصب پکیج‌های مورد نیاز
RUN apk add --no-cache \
    git curl bash \
    postgresql-dev icu-dev oniguruma-dev libzip-dev \
    autoconf g++ make linux-headers

# نصب Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# نصب اکستنشن‌های PHP
RUN pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-ext-install \
        pdo pdo_pgsql intl zip mbstring bcmath pcntl sockets

WORKDIR /var/www/html

# کپی و نصب dependencies
COPY . .
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# دسترسی‌ها
RUN chmod -R 775 storage bootstrap/cache

EXPOSE 8080

# اجرای Reverb
CMD ["php", "artisan", "reverb:start", "--host=0.0.0.0", "--port=8080"]