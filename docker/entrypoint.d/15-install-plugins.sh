#!/bin/sh
set -eu

# Install plugins listed in FATTURINO_PLUGINS (space-separated, no "fatturino/" prefix).
# Cloud-side ProvisionTenant validates Codeberg credentials before deploy via
# `tenants:check-composer-auth`, so this script assumes credentials are correct
# and focuses on the install itself.
#
# Naming: plugin-cloud  ->  https://codeberg.org/fatturino/plugin-cloud.git  ->  fatturino/plugin-cloud
#
# Composer auth: COMPOSER_AUTH_B64 contains base64-encoded JSON
#   {"http-basic":{"codeberg.org":{"username":"u","password":"token"}}}
# We base64 the payload because docker compose env_file handling of quoted JSON
# is inconsistent across versions, and embedding creds in the clone URL breaks
# Composer auth for transitive private dependencies. Materializing auth.json is
# Composer's canonical auth mechanism.

echo "[fatturino][15-install-plugins] start"

if [ -z "${FATTURINO_PLUGINS:-}" ]; then
    echo "[fatturino][15-install-plugins] FATTURINO_PLUGINS empty, skipping"
    exit 0
fi

# Fail loud if git ever falls back to an interactive prompt.
export GIT_TERMINAL_PROMPT=0

cd /var/www/html

# Materialize Composer auth file from base64 env (private repos).
if [ -n "${COMPOSER_AUTH_B64:-}" ]; then
    _composer_home="${COMPOSER_HOME:-/composer}"
    mkdir -p "$_composer_home"
    printf '%s' "$COMPOSER_AUTH_B64" | base64 -d > "$_composer_home/auth.json"
    chmod 600 "$_composer_home/auth.json"
    echo "[fatturino][15-install-plugins] auth.json written to $_composer_home/auth.json"
    unset _composer_home
fi

echo "[fatturino] Installing plugins: $FATTURINO_PLUGINS"
for plugin in $FATTURINO_PLUGINS; do
    # type:git (not vcs) bypasses Composer's Gitea API driver, which would
    # otherwise resolve ssh_url from the API and fail (no openssh client).
    composer config "repositories.${plugin}" \
        '{"type":"git","url":"https://codeberg.org/fatturino/'"${plugin}"'.git"}' --quiet
    composer require "fatturino/${plugin}:dev-main" --no-interaction --no-scripts
done

composer dump-autoload --optimize
php artisan package:discover --ansi

# Rebuild frontend assets so Tailwind scans plugin Blade views.
# Laravel cache invalidation is handled later by AUTORUN_LARAVEL_OPTIMIZE once
# migrations have created the cache table; do NOT call optimize:clear here.
bun run build

echo "[fatturino][15-install-plugins] done"
