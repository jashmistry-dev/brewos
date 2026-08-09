FROM php:8.2-fpm-alpine

# Install extension installer helper
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# System dependencies (runtime utilities for Laravel development)
RUN apk add --no-cache \
    bash \
    curl \
    git \
    unzip \
    zip

# Install PHP extensions required by Laravel/BrewOS
RUN install-php-extensions \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    opcache \
    intl \
    redis

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Working directory
WORKDIR /var/www/html

# Set permissions
RUN addgroup -g 1000 -S www && \
    adduser -u 1000 -S www -G www

# Use www user
USER www

EXPOSE 9000

CMD ["php-fpm"]

