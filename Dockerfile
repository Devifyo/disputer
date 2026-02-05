FROM php:8.2-fpm-alpine

# 1. Install dependencies
RUN apk add --no-cache git curl libpng-dev oniguruma-dev libxml2-dev zip unzip shadow

# 2. Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# 3. Force PHP-FPM to listen on 0.0.0.0:9000
RUN sed -i 's/listen = 127.0.0.1:9000/listen = 0.0.0.0:9000/g' /usr/local/etc/php-fpm.d/www.conf || true

# 4. Get Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 5. Set Work Directory
WORKDIR /var/www

# --- PERMISSION FIX STARTS HERE ---

# A. Create the critical directory structure manually
# This ensures they exist even if your local folder is empty
RUN mkdir -p /var/www/storage/framework/sessions \
    /var/www/storage/framework/views \
    /var/www/storage/framework/cache \
    /var/www/bootstrap/cache

# B. Force ownership to User 82 (Standard Alpine Web User)
# This replaces your manual "sudo chown -R 82:82" command
RUN chown -R 82:82 /var/www

# C. Set Write Permissions (775) so the group can write
RUN chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# --- PERMISSION FIX ENDS HERE ---

# 6. Switch to User 82 (Alpine www-data)
USER 82