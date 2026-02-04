FROM php:8.2-fpm-alpine

# 1. Install dependencies (Using apk for Alpine)
RUN apk add --no-cache git curl libpng-dev oniguruma-dev libxml2-dev zip unzip shadow

# 2. Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# 3. Force PHP-FPM to listen on 0.0.0.0:9000 (Fixes communication issues)
RUN sed -i 's/listen = 127.0.0.1:9000/listen = 0.0.0.0:9000/g' /usr/local/etc/php-fpm.d/www.conf || true

# 4. Get Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 5. Set Permissions
WORKDIR /var/www
RUN chown -R www-data:www-data /var/www

# 6. Switch to user
USER www-data