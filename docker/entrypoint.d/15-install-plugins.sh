#!/bin/sh
set -eu

# Install plugins listed in FATTURINO_PLUGINS (space-separated, no "fatturino/" prefix).
# Cloud-side ProvisionTenant validates Codeberg credentials before deploy via
# `tenants:check-composer-auth`, so this script assumes credentials are correct
# and focuses on the install itself.
#
# Naming: plugin-cloud  ->  https://codeberg.org/fatturino/plugin-cloud.git  ->  fatturino/plugin-cloud
#
# Composer auth: CODEBERG_TOKEN is the plain API token. We build auth.json here
# rather than passing base64-encoded JSON, because some deployment platforms
# (e.g. Uncloud) silently drop long or encoded environment variable values.

echo "[fatturino][15-install-plugins] start"

if [ -z "${FATTURINO_PLUGINS:-}" ]; then
    echo "[fatturino][15-install-plugins] FATTURINO_PLUGINS empty, skipping"
    exit 0
fi

# Diagnostic: log presence (not value) of critical env vars to help trace auth failures.
echo "[fatturino][15-install-plugins] CODEBERG_TOKEN set: $([ -n "${CODEBERG_TOKEN:-}" ] && echo YES || echo NO)"
echo "[fatturino][15-install-plugins] COMPOSER_HOME: ${COMPOSER_HOME:-/composer}"

# Fail loud if git ever falls back to an interactive prompt.
export GIT_TERMINAL_PROMPT=0

cd /var/www/html

if [ -n "${CODEBERG_TOKEN:-}" ]; then
    composer config --global http-basic.codeberg.org fatturino "$CODEBERG_TOKEN"
    echo "[fatturino][15-install-plugins] composer http-basic configured for codeberg.org"

    # Git does NOT read Composer's auth — configure credentials separately.
    # Required because repositories below use type:git, which invokes git clone directly.
    git config --global credential.helper "store --file=/tmp/.git-credentials"
    printf 'https://fatturino:%s@codeberg.org\n' "$CODEBERG_TOKEN" > /tmp/.git-credentials
    chmod 600 /tmp/.git-credentials
    echo "[fatturino][15-install-plugins] git credentials configured for codeberg.org"
else
    echo "[fatturino][15-install-plugins] WARNING: no Codeberg credentials found — private repos will fail"
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
