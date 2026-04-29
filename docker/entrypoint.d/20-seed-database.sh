#!/bin/sh

echo "[fatturino][20-seed-database] start"

if [ ! -f /data/.seeded ]; then
    echo "[fatturino] First boot detected, running seeders..."
    php /var/www/html/artisan db:seed --force --no-interaction
    touch /data/.seeded
    echo "[fatturino] Initial seeding complete"
fi

echo "[fatturino][20-seed-database] done"
