# syntax=docker/dockerfile:1.7

# ==============================================================================
# Stage 1: Install production PHP dependencies
# ==============================================================================
FROM composer:2 AS composer

ARG TARGETPLATFORM

WORKDIR /app

ENV COMPOSER_CACHE_DIR=/tmp/composer-cache

COPY composer.json composer.lock ./

RUN --mount=type=cache,id=composer-${TARGETPLATFORM},target=/tmp/composer-cache,sharing=locked \
    composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --no-scripts

# ==============================================================================
# Stage 2: Build frontend assets with Bun
# ==============================================================================
FROM oven/bun:1 AS frontend

ARG TARGETPLATFORM

WORKDIR /app

COPY package.json bun.lock ./

RUN --mount=type=cache,id=bun-${TARGETPLATFORM},target=/root/.bun/install/cache,sharing=locked \
    bun install --frozen-lockfile

COPY vite.config.js ./
COPY resources/ resources/

RUN bun run build

# ==============================================================================
# Stage 3: Production image
# ==============================================================================
FROM serversideup/php:8.4-fpm-nginx AS production

ARG TARGETPLATFORM

LABEL maintainer="Fatturino <info@fatturino.com>"
LABEL org.opencontainers.image.source="https://codeberg.org/fatturino/fatturino"
LABEL org.opencontainers.image.description="Fatturino - Open Source Italian Electronic Invoicing"

USER root

ENV IPE_PROCESSOR_COUNT=3

RUN --mount=type=cache,id=apt-${TARGETPLATFORM},target=/var/cache/apt,sharing=locked \
    --mount=type=cache,id=apt-lists-${TARGETPLATFORM},target=/var/lib/apt/lists,sharing=locked \
    install-php-extensions bcmath intl gd pgsql \
    && apt-get update && apt-get install -y --no-install-recommends \
        sqlite3 \
        postgresql \
        postgresql-client \
        git \
        nano \
    && ln -s "$(find /usr/lib/postgresql -type f -path '*/bin/psql' -print -quit)" /usr/local/bin/psql \
    && ln -s "$(find /usr/lib/postgresql -type f -path '*/bin/pg_dump' -print -quit)" /usr/local/bin/pg_dump

RUN mkdir -p /data && chown www-data:www-data /data

WORKDIR /var/www/html

COPY --chown=www-data:www-data composer.json composer.lock ./

COPY --chown=www-data:www-data --from=composer /app/vendor/ vendor/

COPY --chown=www-data:www-data . .

COPY --chown=www-data:www-data --from=frontend /app/public/build/ public/build/

RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi

COPY docker/s6-overlay/ /etc/s6-overlay/
COPY docker/entrypoint.d/ /etc/entrypoint.d/

ARG APP_VERSION="0.0.0"
ENV APP_VERSION=${APP_VERSION}

ENV APP_ENV=production \
    APP_DEBUG=false \
    APP_NAME=Fatturino \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=pgsql \
    DB_HOST=127.0.0.1 \
    DB_PORT=5432 \
    DB_DATABASE=fatturino \
    DB_USERNAME=fatturino \
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
