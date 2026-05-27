#!/bin/sh
set -eu

echo "[fatturino][15-migrate] running migrations..."
php /var/www/html/artisan migrate --force --no-interaction
echo "[fatturino][15-migrate] done"
