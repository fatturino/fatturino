#!/bin/sh
set -eu

# Migrations must run before plugins install: the `plugin:install` command
# writes to the `plugins` table, which requires the schema to exist.
echo "[fatturino][15-migrate] running migrations..."
php /var/www/html/artisan migrate --force --no-interaction
echo "[fatturino][15-migrate] done"
