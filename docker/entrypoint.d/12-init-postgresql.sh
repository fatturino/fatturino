#!/bin/sh
set -eu

readonly PGDATA=/data/postgresql

echo "[fatturino][12-init-postgresql] start"

if [ "${DB_CONNECTION:-pgsql}" != "pgsql" ]; then
    echo "[fatturino][12-init-postgresql] skipped for DB_CONNECTION=${DB_CONNECTION:-}"
    exit 0
fi

postgres_bin() {
    find /usr/lib/postgresql -path "*/bin/$1" -type f -print -quit
}

readonly INITDB="$(postgres_bin initdb)"
readonly PG_CTL="$(postgres_bin pg_ctl)"
readonly PSQL="$(postgres_bin psql)"

if [ -z "${INITDB}" ] || [ -z "${PG_CTL}" ] || [ -z "${PSQL}" ]; then
    echo "[fatturino][12-init-postgresql] PostgreSQL binaries are missing" >&2
    exit 1
fi

if [ -f /data/database.sqlite ] && [ ! -f /data/postgresql/.sqlite-migration-complete ] && [ "${MIGRATION_MODE:-0}" != "1" ]; then
    echo "[fatturino][12-init-postgresql] legacy SQLite data detected; set MIGRATION_MODE=1 only after completing the migration runbook" >&2
    exit 1
fi

: "${DB_DATABASE:?DB_DATABASE must be set}"
: "${DB_USERNAME:?DB_USERNAME must be set}"
: "${DB_PASSWORD:?DB_PASSWORD must be set}"
: "${POSTGRES_SUPERUSER_PASSWORD:?POSTGRES_SUPERUSER_PASSWORD must be set}"

if [ ! -f "${PGDATA}/PG_VERSION" ]; then
    password_file=$(mktemp)
    cleanup() {
        rm -f "${password_file}"
    }
    trap cleanup EXIT

    printf '%s' "${POSTGRES_SUPERUSER_PASSWORD}" > "${password_file}"
    chown postgres:postgres "${password_file}"
    chmod 600 "${password_file}"

    runuser -u postgres -- "${INITDB}" --pgdata="${PGDATA}" --username=postgres --pwfile="${password_file}" --auth-local=trust --auth-host=scram-sha-256 --encoding=UTF8 --locale=C.UTF-8

    cat >> "${PGDATA}/postgresql.conf" <<'EOF'
listen_addresses = '127.0.0.1'
port = 5432
password_encryption = 'scram-sha-256'
timezone = 'Europe/Rome'
EOF

    runuser -u postgres -- "${PG_CTL}" -D "${PGDATA}" -w start

    runuser -u postgres -- "${PSQL}" --set=ON_ERROR_STOP=1 --username=postgres --dbname=postgres --set=db_username="${DB_USERNAME}" --set=db_password="${DB_PASSWORD}" --set=db_database="${DB_DATABASE}" <<'SQL'
CREATE ROLE :"db_username" LOGIN PASSWORD :'db_password' NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT;
CREATE DATABASE :"db_database" OWNER :"db_username" TEMPLATE template0 ENCODING 'UTF8';
SQL

    runuser -u postgres -- "${PG_CTL}" -D "${PGDATA}" -m fast stop
    cleanup
    trap - EXIT
    echo "[fatturino][12-init-postgresql] initialized PostgreSQL cluster"
fi

echo "[fatturino][12-init-postgresql] done"
