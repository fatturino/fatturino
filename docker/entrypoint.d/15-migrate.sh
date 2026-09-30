#!/bin/sh
set -eu

echo "[fatturino][15-migrate] running migrations"
php /var/www/html/artisan migrate --force --no-interaction

# A legacy SQLite source is migrated only while the application, worker and
# scheduler are still stopped. A marker makes restarts idempotent.
if [ "${DB_CONNECTION:-pgsql}" = "pgsql" ] && [ -f /data/database.sqlite ] && [ ! -f /data/postgresql/.sqlite-migration-complete ]; then
    echo "[fatturino][15-migrate] importing legacy SQLite data"
    php /var/www/html/artisan database:migration-preflight --source=/data/database.sqlite --require-postgres
    php /var/www/html/artisan database:import-sqlite-to-postgres --source=/data/database.sqlite
    php /var/www/html/artisan database:verify-sqlite-postgres --source=/data/database.sqlite
    touch /data/postgresql/.sqlite-migration-complete
    chown postgres:postgres /data/postgresql/.sqlite-migration-complete
    chmod 600 /data/postgresql/.sqlite-migration-complete
    # The imported database already contains application state. Do not run first-boot
    # seeders after the cutover, as they would write into the restored target.
    touch /data/.seeded
    chown www-data:www-data /data/.seeded
    chmod 600 /data/.seeded
    echo "[fatturino][15-migrate] SQLite migration verified and marked complete"
fi

echo "[fatturino][15-migrate] done"
