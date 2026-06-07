# Stage 1: Build assets
FROM node:20-alpine AS assets-builder
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

# Stage 2: PHP Application
FROM php:8.4-fpm-alpine

# Install system dependencies
# Tesseract: required for the local OCR provider (`/admin/ocr` → Tesseract).
#   - tesseract-ocr: the binary itself
#   - tesseract-ocr-data-*: language packs. The defaults `eng` and `msa` cover
#     English + Bahasa Malaysia; add ind/chi-sim/chi-tra/tha when needed.
RUN apk add --no-cache \
    nginx \
    supervisor \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    oniguruma-dev \
    libxml2-dev \
    tesseract-ocr \
    tesseract-ocr-data-eng \
    tesseract-ocr-data-msa \
    tesseract-ocr-data-ind \
    tesseract-ocr-data-chi_sim \
    poppler-utils \
    imagemagick

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    gd \
    pdo_mysql \
    mysqli \
    mbstring \
    zip \
    bcmath \
    xml \
    opcache

# Production-tuned OPcache config (validate_timestamps=0, larger caches,
# JIT on). Drops first-byte latency 30–50% vs. defaults on Laravel.
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Lock down ImageMagick. We invoke it only on user-uploaded images during
# OCR preprocessing, so deny PDF/PS/MSL/MVG/URL coders that have a long
# CVE history (Ghostscript-class issues, server-side scripting). Resource
# limits prevent a single malicious image from OOMing the container.
COPY docker/imagemagick/policy.xml /tmp/imagemagick-policy.xml
RUN set -eu; \
    # Discover where the installed ImageMagick build expects policy.xml.
    # Alpine's `imagemagick` is v7 today; this fallback also covers v6.
    POLICY_DIR="$(magick -list configure 2>/dev/null | awk '/^CONFIGURE_PATH /{print $2; exit}')"; \
    if [ -z "$POLICY_DIR" ]; then \
        POLICY_DIR="$(convert -list configure 2>/dev/null | awk '/^CONFIGURE_PATH /{print $2; exit}')"; \
    fi; \
    if [ -z "$POLICY_DIR" ]; then \
        # Fall back to common paths if -list query returned nothing.
        for p in /etc/ImageMagick-7 /etc/ImageMagick-6 /usr/local/etc/ImageMagick-7; do \
            [ -d "$p" ] && POLICY_DIR="$p" && break; \
        done; \
    fi; \
    if [ -z "$POLICY_DIR" ]; then \
        echo "ImageMagick policy directory not found" >&2; exit 1; \
    fi; \
    install -m 0644 /tmp/imagemagick-policy.xml "$POLICY_DIR/policy.xml"; \
    rm /tmp/imagemagick-policy.xml; \
    echo "ImageMagick policy installed at $POLICY_DIR/policy.xml"

# Copy Composer from official image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application code
COPY . .

# Copy built assets from builder stage
COPY --from=assets-builder /app/public/build ./public/build

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set permissions and create log directories
RUN mkdir -p /var/log/supervisor && \
    chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/log/supervisor

# Copy Nginx and Supervisor configurations
COPY docker/nginx/ecs-default.conf /etc/nginx/http.d/default.conf
COPY docker/supervisor.conf /etc/supervisor/conf.d/supervisor.conf

# Copy entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Expose port
EXPOSE 80

# Use entrypoint script, then keep the container alive via supervisor
ENTRYPOINT ["entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisor.conf"]
