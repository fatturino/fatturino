#!/bin/sh

echo "[fatturino][20-seed-database] start"

if [ ! -f /data/.seeded ]; then
    echo "[fatturino] First boot detected, running seeders..."
    php /var/www/html/artisan db:seed --force --no-interaction
    touch /data/.seeded
    echo "[fatturino] Initial seeding complete"
fi

# Ensure www-data owns everything after migrations/seeders have run
chown -R www-data:www-data /data

echo "[fatturino][20-seed-database] done"
