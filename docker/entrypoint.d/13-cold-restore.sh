#!/bin/sh
set -eu

readonly RESTORE_MARKER=/data/.cold-restore-complete
readonly RESTORE_TYPE_FILE=/data/.cold-restore-database-type

if [ -z "${RESTORE_BACKUP_S3_KEY:-}" ]; then
    exit 0
fi

restore_key_hash=$(printf '%s' "${RESTORE_BACKUP_S3_KEY}" | sha256sum | awk '{print $1}')

if [ -f "${RESTORE_MARKER}" ]; then
    if [ "$(sed -n '1p' "${RESTORE_MARKER}")" = "${restore_key_hash}" ] && [ -n "$(sed -n '2p' "${RESTORE_MARKER}")" ]; then
        echo "[fatturino][13-cold-restore] requested backup was already restored"
        exit 0
    fi

    echo "[fatturino][13-cold-restore] a different backup was already restored; remove ${RESTORE_MARKER} only after an explicit recovery decision" >&2
    exit 1
fi

echo "[fatturino][13-cold-restore] restoring backup from S3 before application services start"
rm -f "${RESTORE_TYPE_FILE}"
php /var/www/html/artisan app:restore-backup --s3-key="${RESTORE_BACKUP_S3_KEY}" --s3-key-is-full-path --database-type-file="${RESTORE_TYPE_FILE}" --cold --force

restore_type=$(cat "${RESTORE_TYPE_FILE}")
case "${restore_type}" in
    sqlite|sqlite-sql|sql) ;;
    *)
        echo "[fatturino][13-cold-restore] restore completed without a recognized database type" >&2
        exit 1
        ;;
esac

# A restored application database must not be replaced by first-boot seeders.
printf '%s\n%s\n' "${restore_key_hash}" "${restore_type}" > "${RESTORE_MARKER}"
rm -f "${RESTORE_TYPE_FILE}"
touch /data/.seeded
chown www-data:www-data "${RESTORE_MARKER}" /data/.seeded
chmod 600 "${RESTORE_MARKER}" /data/.seeded

echo "[fatturino][13-cold-restore] backup restore completed"
