#!/bin/sh

echo "[fatturino][10-setup-data] start"

# Create persistent data directory structure on first boot
mkdir -p /data/storage/app/private/imports
mkdir -p /data/storage/app/private/documents/xml/sales
mkdir -p /data/storage/app/private/documents/xml/purchase
mkdir -p /data/storage/app/private/documents/xml/credit-notes
mkdir -p /data/storage/app/private/documents/xml/self-invoices
mkdir -p /data/storage/app/private/documents/pdf/sales
mkdir -p /data/storage/app/private/documents/pdf/credit-notes
mkdir -p /data/storage/app/public
mkdir -p /data/storage/logs

# Preserve legacy SQLite data as a migration source. New installations use PostgreSQL.
if [ "${DB_CONNECTION:-pgsql}" = "sqlite" ] && [ ! -f /data/database.sqlite ]; then
    touch /data/database.sqlite
    echo "[fatturino] Created new SQLite database at /data/database.sqlite"
fi

# PostgreSQL data must remain owned by its dedicated system user.
mkdir -p /data/postgresql
chown postgres:postgres /data/postgresql
chmod 700 /data/postgresql

# Symlink storage subdirectories to the persistent /data volume
rm -rf /var/www/html/storage/app/private
ln -sf /data/storage/app/private /var/www/html/storage/app/private

rm -rf /var/www/html/storage/app/public
ln -sf /data/storage/app/public /var/www/html/storage/app/public

rm -rf /var/www/html/storage/logs
ln -sf /data/storage/logs /var/www/html/storage/logs

# Enable WAL mode only while operating the legacy SQLite deployment.
if [ "${DB_CONNECTION:-pgsql}" = "sqlite" ] && command -v sqlite3 > /dev/null 2>&1; then
    sqlite3 /data/database.sqlite "PRAGMA journal_mode=WAL;" > /dev/null 2>&1
fi

# Do not change PostgreSQL ownership while preparing application storage.
chown -R www-data:www-data /data/storage

echo "[fatturino][10-setup-data] done"
