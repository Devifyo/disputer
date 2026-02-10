FROM php:8.2-fpm-alpine

# --------------------------------------------------
# 1. System Dependencies
# --------------------------------------------------
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    oniguruma-dev \
    libxml2-dev \
    zip \
    unzip \
    shadow

# --------------------------------------------------
# 2. PHP Extensions
# --------------------------------------------------
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd

# --------------------------------------------------
# 3. PHP-FPM Config
# --------------------------------------------------
RUN sed -i 's|listen = 127.0.0.1:9000|listen = 0.0.0.0:9000|' \
    /usr/local/etc/php-fpm.d/www.conf

# --------------------------------------------------
# 4. Composer
# --------------------------------------------------
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# --------------------------------------------------
# 5. Application Root (CRITICAL)
# --------------------------------------------------
WORKDIR /var/www/html

# --------------------------------------------------
# 6. Laravel Runtime Directories
# --------------------------------------------------
RUN mkdir -p \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/cache \
    bootstrap/cache

# --------------------------------------------------
# 7. Permissions (Laravel-safe)
# --------------------------------------------------
RUN chown -R 82:82 /var/www/html \
 && chmod -R 775 storage bootstrap/cache

# --------------------------------------------------
# 8. Run as non-root
# --------------------------------------------------
USER 82
