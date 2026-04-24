#!/bin/sh

# Run migrations on every boot (idempotent — Laravel tracks which have already run)
echo "[fatturino] Running database migrations..."
php /var/www/html/artisan migrate --force --no-interaction

# Run seeders on first boot only (flag file prevents re-seeding)
if [ ! -f /data/.seeded ]; then
    echo "[fatturino] First boot detected, running seeders..."

    # Use DemoSeeder when demo-mode plugin is installed
    if composer show fatturino/plugin-demo-mode > /dev/null 2>&1; then
        echo "[fatturino] Demo mode plugin detected, using DemoSeeder..."
        php /var/www/html/artisan db:seed --class='Fatturino\DemoMode\Database\Seeders\DemoSeeder' --force --no-interaction
    else
        php /var/www/html/artisan db:seed --force --no-interaction
    fi

    touch /data/.seeded
    echo "[fatturino] Initial seeding complete"
fi
