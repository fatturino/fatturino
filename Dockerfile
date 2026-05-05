# ==============================================================================
# Stage 1: Install PHP dependencies (needed for Tailwind CSS source scanning)
# ==============================================================================
FROM composer:2 AS composer

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --no-scripts

# ==============================================================================
# Stage 2: Build frontend assets with Bun
# ==============================================================================
FROM oven/bun:1 AS frontend

WORKDIR /app

# Copy dependency files for layer caching
COPY package.json bun.lock ./

# Install frontend dependencies
RUN bun install --frozen-lockfile

# Copy source files needed for the Vite build
COPY vite.config.js ./
COPY resources/ resources/

# Copy Mary UI components so Tailwind CSS 4 can scan @source classes
COPY --from=composer /app/vendor/robsontenorio/mary/ vendor/robsontenorio/mary/

# Build production assets (output: public/build/)
RUN bun run build

# ==============================================================================
# Stage 3: Production image
# Built on php-base which has extensions pre-compiled (avoids slow QEMU builds).
# php-base viene rebuildato automaticamente quando docker/php-base.Dockerfile
# cambia, oppure manualmente dalla pipeline Woodpecker.
# L'immagine php-base è multi-arch (AMD64 + ARM64), quindi buildx risolve
# automaticamente l'architettura corretta.
# ==============================================================================
FROM codeberg.org/fatturino/fatturino:php-base AS production

LABEL maintainer="Fatturino <info@fatturino.com>"
LABEL org.opencontainers.image.source="https://codeberg.org/fatturino/fatturino"
LABEL org.opencontainers.image.description="Fatturino - Open Source Italian Electronic Invoicing"

# Switch back to www-data for application setup
USER www-data

WORKDIR /var/www/html

# Copy composer files for dependency resolution (plugins fetched via VCS)
COPY --chown=www-data:www-data composer.json composer.lock ./

# Install PHP dependencies (production only)
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

# Copy application source code
COPY --chown=www-data:www-data . .

# Copy built frontend assets from the frontend stage
COPY --chown=www-data:www-data --from=frontend /app/public/build/ public/build/

# Copy bun binary + node_modules for runtime asset rebuild (when plugins are installed)
COPY --from=frontend /usr/local/bin/bun /usr/local/bin/bun
COPY --chown=www-data:www-data --from=frontend /app/node_modules/ node_modules/
COPY --chown=www-data:www-data --from=frontend /app/vite.config.js vite.config.js
COPY --chown=www-data:www-data --from=frontend /app/package.json package.json

# Run post-install scripts (package discovery, autoload optimization)
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi

# Copy S6 Overlay service definitions (queue worker + scheduler)
COPY docker/s6-overlay/ /etc/s6-overlay/

# Copy entrypoint scripts (data dir setup, database seeding)
COPY docker/entrypoint.d/ /etc/entrypoint.d/

# Accept version from build args (injected by CI/CD)
ARG APP_VERSION="0.0.0"
ENV APP_VERSION=${APP_VERSION}

# Production environment defaults
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/data/database.sqlite \
    SESSION_DRIVER=database \
    QUEUE_CONNECTION=database \
    CACHE_STORE=database \
    FILESYSTEM_DISK=local \
    AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_STORAGE_LINK=true \
    AUTORUN_LARAVEL_MIGRATION=false \
    AUTORUN_LARAVEL_MIGRATION_ISOLATION=false \
    AUTORUN_LARAVEL_OPTIMIZE=true \
    PHP_OPCACHE_ENABLE=1 \
    PHP_DATE_TIMEZONE="Europe/Rome" \
    SSL_MODE=off

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -f http://localhost:8080/up || exit 1
