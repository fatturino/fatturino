#!/bin/sh
set -eu

echo "[fatturino][15-migrate] running migrations"
php /var/www/html/artisan migrate --force --no-interaction

# A legacy SQLite source is migrated only while the application, worker and
# scheduler are still stopped. A marker makes restarts idempotent.
if [ "${DB_CONNECTION:-pgsql}" = "pgsql" ] && [ -f /data/database.sqlite ] && [ ! -f /data/postgresql/.sqlite-migration-complete ]; then
    if [ "${MIGRATION_MODE:-0}" != "1" ]; then
        echo "[fatturino][15-migrate] legacy SQLite data detected; set MIGRATION_MODE=1 for the approved offline cutover" >&2
        exit 1
    fi

    echo "[fatturino][15-migrate] importing legacy SQLite data"
    php /var/www/html/artisan database:migration-preflight --source=/data/database.sqlite --require-postgres
    php /var/www/html/artisan database:import-sqlite-to-postgres --source=/data/database.sqlite
    php /var/www/html/artisan database:verify-sqlite-postgres --source=/data/database.sqlite
    touch /data/postgresql/.sqlite-migration-complete
    chown postgres:postgres /data/postgresql/.sqlite-migration-complete
    chmod 600 /data/postgresql/.sqlite-migration-complete
    echo "[fatturino][15-migrate] SQLite migration verified and marked complete"
fi

echo "[fatturino][15-migrate] done"
