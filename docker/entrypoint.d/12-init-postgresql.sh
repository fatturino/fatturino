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
readonly PG_ISREADY="$(postgres_bin pg_isready)"
readonly PSQL="$(postgres_bin psql)"

if [ -z "${INITDB}" ] || [ -z "${PG_CTL}" ] || [ -z "${PG_ISREADY}" ] || [ -z "${PSQL}" ]; then
    echo "[fatturino][12-init-postgresql] PostgreSQL binaries are missing" >&2
    exit 1
fi

if [ -f /data/database.sqlite ] && [ ! -f "${PGDATA}/.sqlite-migration-complete" ] && [ "${MIGRATION_MODE:-0}" != "1" ]; then
    echo "[fatturino][12-init-postgresql] legacy SQLite data detected; set MIGRATION_MODE=1 only after completing the migration runbook" >&2
    exit 1
fi

: "${DB_DATABASE:?DB_DATABASE must be set}"
: "${DB_USERNAME:?DB_USERNAME must be set}"
: "${DB_PASSWORD:?DB_PASSWORD must be set}"
: "${POSTGRES_SUPERUSER_PASSWORD:?POSTGRES_SUPERUSER_PASSWORD must be set}"

chown postgres:postgres "${PGDATA}"
chmod 700 "${PGDATA}"

if [ ! -f "${PGDATA}/PG_VERSION" ]; then
    password_file=$(mktemp)
    trap 'rm -f "${password_file}"' EXIT

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

    rm -f "${password_file}"
    trap - EXIT
fi

if ! runuser -u postgres -- "${PG_ISREADY}" --host=127.0.0.1 --port=5432 --quiet; then
    runuser -u postgres -- "${PG_CTL}" -D "${PGDATA}" -w start
fi

if ! runuser -u postgres -- "${PG_ISREADY}" --host=127.0.0.1 --port=5432 --quiet; then
    echo "[fatturino][12-init-postgresql] PostgreSQL did not become ready" >&2
    exit 1
fi

if ! runuser -u postgres -- "${PSQL}" --tuples-only --no-align --username=postgres --dbname=postgres --set=db_username="${DB_USERNAME}" <<'SQL' | grep -qx '1'; then
SELECT 1 FROM pg_roles WHERE rolname = :'db_username';
SQL
    runuser -u postgres -- "${PSQL}" --set=ON_ERROR_STOP=1 --username=postgres --dbname=postgres --set=db_username="${DB_USERNAME}" --set=db_password="${DB_PASSWORD}" <<'SQL'
CREATE ROLE :"db_username" LOGIN PASSWORD :'db_password' NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT;
SQL
fi

if ! runuser -u postgres -- "${PSQL}" --tuples-only --no-align --username=postgres --dbname=postgres --set=db_database="${DB_DATABASE}" <<'SQL' | grep -qx '1'; then
SELECT 1 FROM pg_database WHERE datname = :'db_database';
SQL
    runuser -u postgres -- "${PSQL}" --set=ON_ERROR_STOP=1 --username=postgres --dbname=postgres --set=db_username="${DB_USERNAME}" --set=db_database="${DB_DATABASE}" <<'SQL'
CREATE DATABASE :"db_database" OWNER :"db_username" TEMPLATE template0 ENCODING 'UTF8';
SQL
fi

echo "[fatturino][12-init-postgresql] PostgreSQL cluster ready"

echo "[fatturino][12-init-postgresql] done"
