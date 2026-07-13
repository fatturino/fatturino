#!/usr/bin/env bash
set -euo pipefail

echo "[entrypoint] Setting storage permissions..."

mkdir -p /var/www/html/storage/logs
chown -R www-data:www-data /var/www/html/storage
chmod -R 775 /var/www/html/storage

mkdir -p /data/logs
chown -R www-data:www-data /data/logs
chmod -R 775 /data/logs

echo "[entrypoint] Storage permissions OK."
