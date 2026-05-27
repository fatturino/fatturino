#!/bin/sh

echo "[fatturino][20-seed-database] start"

if [ ! -f /data/.seeded ]; then
    echo "[fatturino] First boot detected"

    case "${FATTURINO_DEMO:-false}" in
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

# Ensure www-data owns everything after migrations/seeders have run
chown -R www-data:www-data /data

echo "[fatturino][20-seed-database] done"
