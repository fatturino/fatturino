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

COPY package.json bun.lock ./

RUN bun install --frozen-lockfile

COPY vite.config.js ./
COPY resources/ resources/

COPY --from=composer /app/vendor/robsontenorio/mary/ vendor/robsontenorio/mary/

RUN bun run build

# ==============================================================================
# Stage 3: Production image
# ==============================================================================
FROM serversideup/php:8.4-fpm-nginx AS production

LABEL maintainer="Fatturino <info@fatturino.com>"
LABEL org.opencontainers.image.source="https://codeberg.org/fatturino/fatturino"
LABEL org.opencontainers.image.description="Fatturino - Open Source Italian Electronic Invoicing"

USER root

ENV IPE_PROCESSOR_COUNT=3

RUN install-php-extensions bcmath intl gd \
    && apt-get update && apt-get install -y --no-install-recommends sqlite3 git nano 
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Replace s6-overlay v3 user bundle (uses suexec which fails under Dokku)
# with services that use setpriv for privilege dropping.
RUN rm -rf /etc/s6-overlay/s6-rc.d/user \
    && rm -rf /etc/s6-overlay/s6-rc.d/nginx \
    && rm -rf /etc/s6-overlay/s6-rc.d/php-fpm \
    && mkdir -p /etc/s6-overlay/s6-rc.d/nginx /etc/s6-overlay/s6-rc.d/php-fpm \
    && echo longrun > /etc/s6-overlay/s6-rc.d/nginx/type \
    && printf '#!/command/execlineb -P\nfdmove -c 2 1\nsetpriv --reuid=www-data --regid=www-data --clear-groups /usr/sbin/nginx -g "daemon off;"\n' > /etc/s6-overlay/s6-rc.d/nginx/run \
    && chmod +x /etc/s6-overlay/s6-rc.d/nginx/run \
    && echo longrun > /etc/s6-overlay/s6-rc.d/php-fpm/type \
    && printf '#!/command/execlineb -P\nfdmove -c 2 1\nsetpriv --reuid=www-data --regid=www-data --clear-groups /usr/sbin/php-fpm8.4 --nodaemonize --fpm-config /etc/php/8.4/fpm/php-fpm.conf\n' > /etc/s6-overlay/s6-rc.d/php-fpm/run \
    && chmod +x /etc/s6-overlay/s6-rc.d/php-fpm/run \
    && mkdir -p /etc/s6-overlay/s6-rc.d/default/contents.d \
    && echo nginx > /etc/s6-overlay/s6-rc.d/default/contents.d/nginx \
    && echo php-fpm > /etc/s6-overlay/s6-rc.d/default/contents.d/php-fpm

RUN mkdir -p /data && chown www-data:www-data /data

USER www-data

WORKDIR /var/www/html

COPY --chown=www-data:www-data composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

COPY --chown=www-data:www-data . .

COPY --chown=www-data:www-data --from=frontend /app/public/build/ public/build/

COPY --from=frontend /usr/local/bin/bun /usr/local/bin/bun
COPY --chown=www-data:www-data --from=frontend /app/node_modules/ node_modules/
COPY --chown=www-data:www-data --from=frontend /app/vite.config.js vite.config.js
COPY --chown=www-data:www-data --from=frontend /app/package.json package.json

RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi

COPY docker/s6-overlay/ /etc/s6-overlay/
COPY docker/entrypoint.d/ /etc/entrypoint.d/

ARG APP_VERSION="0.0.0"
ENV APP_VERSION=${APP_VERSION}

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
