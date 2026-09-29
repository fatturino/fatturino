#!/bin/sh
set -eu

root=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)

for script in "$root"/docker/entrypoint.d/*.sh; do
    sh -n "$script"
done

grep -Fq 'APP_NAME=Fatturino \' "$root/Dockerfile"
grep -Fq 'PG_ISREADY=' "$root/docker/entrypoint.d/12-init-postgresql.sh"
grep -Fq 'pg_isready' "$root/docker/entrypoint.d/12-init-postgresql.sh"
test -f "$root/docker/entrypoint.d/25-stop-bootstrap-postgresql.sh"
grep -Fq "postgres=\$(find /usr/lib/postgresql -path '*/bin/postgres'" "$root/docker/s6-overlay/s6-rc.d/postgresql/run"
grep -Fq 'exec runuser -u postgres -- "${postgres}" -D /data/postgresql' "$root/docker/s6-overlay/s6-rc.d/postgresql/run"
! grep -Fq 'chown -R www-data:www-data /data' "$root/docker/entrypoint.d/20-seed-database.sh"
grep -Fq 'chown -R www-data:www-data /data/storage' "$root/docker/entrypoint.d/20-seed-database.sh"

echo 'Docker bootstrap static checks passed.'
