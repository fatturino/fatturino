#!/bin/sh
set -eu

if [ "${DB_CONNECTION:-pgsql}" != "pgsql" ]; then
    exit 0
fi

PG_CTL=$(find /usr/lib/postgresql -path '*/bin/pg_ctl' -type f -print -quit)

if [ -z "${PG_CTL}" ]; then
    echo "[fatturino][25-stop-bootstrap-postgresql] PostgreSQL pg_ctl binary is missing" >&2
    exit 1
fi

if runuser -u postgres -- "${PG_CTL}" -D /data/postgresql status > /dev/null 2>&1; then
    runuser -u postgres -- "${PG_CTL}" -D /data/postgresql -m fast -w stop
fi

echo "[fatturino][25-stop-bootstrap-postgresql] done"
