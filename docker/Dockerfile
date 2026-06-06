# BukuCloud self-hosted Docker image.
#
# Multi-stage build:
#   1. composer  — install PHP deps (no dev) into vendor/
#   2. assets    — install npm deps and run `npm run build`
#   3. runtime   — slim PHP-FPM + nginx-less app container
#
# The runtime image deliberately does NOT include nginx — we ship a
# separate nginx container in docker-compose.yml so customers can swap
# it for Caddy / Traefik / their own load balancer without rebuilding
# the app image.

# ─────────────────────────────────────────────────────────────────────
# Stage 1 — composer
# ─────────────────────────────────────────────────────────────────────
FROM composer:2.7 AS composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction \
    --no-progress

COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# ─────────────────────────────────────────────────────────────────────
# Stage 2 — assets (frontend bundle)
# ─────────────────────────────────────────────────────────────────────
FROM node:20-alpine AS assets

WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci --no-audit --no-fund

COPY . .
COPY --from=composer /app/vendor /app/vendor
RUN npm run build

# ─────────────────────────────────────────────────────────────────────
# Stage 3 — runtime
# ─────────────────────────────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS runtime

# System packages we actually use:
#   - icu-dev / oniguruma-dev / libxml2-dev → typical Laravel deps
#   - imagemagick / ghostscript / poppler-utils → OCR + PDF preview
#   - mariadb-client → for `php artisan tinker` + dump-on-demand support
#   - tini → PID 1 reaper (avoids zombie processes from queued jobs)
RUN apk add --no-cache \
        bash \
        curl \
        tini \
        icu-dev \
        oniguruma-dev \
        libxml2-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        libwebp-dev \
        zlib-dev \
        libzip-dev \
        imagemagick \
        imagemagick-dev \
        ghostscript \
        poppler-utils \
        mariadb-client \
        nodejs \
        ca-certificates \
        tzdata \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        intl \
        mbstring \
        pdo_mysql \
        gd \
        zip \
        opcache \
    && pecl install imagick redis \
    && docker-php-ext-enable imagick redis \
    && apk del --no-cache imagemagick-dev libxml2-dev oniguruma-dev libpng-dev libjpeg-turbo-dev libwebp-dev zlib-dev libzip-dev

# Set up the application user (don't run as root inside the container).
RUN addgroup -g 1000 bukucloud \
    && adduser -D -G bukucloud -u 1000 bukucloud

WORKDIR /var/www/html

# Copy production-built artefacts. We deliberately don't COPY . . here
# — only the files we need at runtime end up in the layer.
COPY --chown=bukucloud:bukucloud --from=composer /app /var/www/html
COPY --chown=bukucloud:bukucloud --from=assets   /app/public/build /var/www/html/public/build

# Default opcache + fpm tunings — production-friendly, customers can
# override by mounting their own /usr/local/etc/php/conf.d/.
COPY --chown=root:root docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-bukucloud-opcache.ini
COPY --chown=root:root docker/php/php.ini     /usr/local/etc/php/conf.d/zz-bukucloud-runtime.ini

# Entrypoint runs migrations + cache:warm + storage:link on every boot
# (idempotent), then hands off to PHP-FPM.
COPY --chown=root:root docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# Storage / bootstrap/cache need to be writable. We create them empty
# in case the customer mounts a fresh volume.
RUN mkdir -p /var/www/html/storage/framework/{sessions,cache,views} \
             /var/www/html/storage/logs \
             /var/www/html/bootstrap/cache \
    && chown -R bukucloud:bukucloud /var/www/html/storage /var/www/html/bootstrap/cache

USER bukucloud

EXPOSE 9000

ENTRYPOINT ["/sbin/tini", "--", "/usr/local/bin/entrypoint"]
CMD ["php-fpm"]

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD php artisan license:status --json >/dev/null 2>&1 || exit 1
