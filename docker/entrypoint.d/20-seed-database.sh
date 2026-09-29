#!/bin/sh
set -eu

echo "[fatturino][20-seed-database] start"

if [ ! -f /data/.seeded ]; then
    echo "[fatturino] First boot detected"

    case "${DEMO_MODE:-false}" in
        1|true|TRUE|yes|YES|on|ON)
            echo "[fatturino] Demo mode enabled, running demo:refresh..."
            php /var/www/html/artisan demo:refresh --no-interaction
            ;;
        *)
            echo "[fatturino] Running default seeders..."
            php /var/www/html/artisan db:seed --force --no-interaction
            ;;
    esac

    touch /data/.seeded
    echo "[fatturino] Initial seeding complete"
fi

# Application data belongs to www-data. PostgreSQL owns /data/postgresql and
# must never be included here.
chown -R www-data:www-data /data/storage
chown www-data:www-data /data/.seeded

echo "[fatturino][20-seed-database] done"
