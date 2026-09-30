#!/bin/sh
set -eu

root=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)

for script in "$root"/docker/entrypoint.d/*.sh; do
    sh -n "$script"
done

grep -Fq 'APP_NAME=Fatturino \' "$root/Dockerfile"
grep -Fq '# syntax=docker/dockerfile:1.7' "$root/Dockerfile"
grep -Fq -- '--from=composer /app/vendor/ vendor/' "$root/Dockerfile"
grep -Fq 'type=cache,id=composer-${TARGETPLATFORM}' "$root/Dockerfile"
grep -Fq 'type=cache,id=bun-${TARGETPLATFORM}' "$root/Dockerfile"
grep -Fq 'HEALTHCHECK --interval=30s --timeout=5s --start-period=15m --retries=3' "$root/Dockerfile"
grep -Fq 'PG_ISREADY=' "$root/docker/entrypoint.d/12-init-postgresql.sh"
grep -Fq 'pg_isready' "$root/docker/entrypoint.d/12-init-postgresql.sh"
grep -Fq "include_if_exists = 'fatturino.conf'" "$root/docker/entrypoint.d/12-init-postgresql.sh"
grep -Fq 'listen_addresses = '\''*'\''' "$root/docker/entrypoint.d/12-init-postgresql.sh"
grep -Fq 'host    all             all             samenet                 scram-sha-256' "$root/docker/entrypoint.d/12-init-postgresql.sh"
test -f "$root/docker/entrypoint.d/25-stop-bootstrap-postgresql.sh"
grep -Fq "postgres=\$(find /usr/lib/postgresql -path '*/bin/postgres'" "$root/docker/s6-overlay/s6-rc.d/postgresql/run"
grep -Fq 'exec runuser -u postgres -- "${postgres}" -D /data/postgresql' "$root/docker/s6-overlay/s6-rc.d/postgresql/run"
! grep -Fq 'chown -R www-data:www-data /data' "$root/docker/entrypoint.d/20-seed-database.sh"
grep -Fq 'chown -R www-data:www-data /data/storage' "$root/docker/entrypoint.d/20-seed-database.sh"
grep -Fq 'if: github.event_name != '\''push'\''' "$root/.github/workflows/build.yml"
grep -Fq 'platforms: linux/amd64' "$root/.github/workflows/build.yml"
grep -Fq 'platforms: linux/amd64,linux/arm64' "$root/.github/workflows/build.yml"
grep -Fq 'cache-from: type=gha,scope=fatturino-production' "$root/.github/workflows/build.yml"
grep -Fq 'cache-to: type=gha,mode=max,scope=fatturino-production' "$root/.github/workflows/build.yml"

echo 'Docker bootstrap static checks passed.'
