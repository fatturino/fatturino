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

# Diagnostic: log presence (not value) of critical env vars to help trace auth failures.
echo "[fatturino][15-install-plugins] COMPOSER_AUTH_B64 set: $([ -n "${COMPOSER_AUTH_B64:-}" ] && echo YES || echo NO)"
echo "[fatturino][15-install-plugins] COMPOSER_HOME: ${COMPOSER_HOME:-/composer}"

# Fail loud if git ever falls back to an interactive prompt.
export GIT_TERMINAL_PROMPT=0

cd /var/www/html

# Materialize Composer auth file from base64 env (primary path) or raw JSON env (fallback).
_composer_home="${COMPOSER_HOME:-/composer}"
if [ -n "${COMPOSER_AUTH_B64:-}" ]; then
    mkdir -p "$_composer_home"
    printf '%s' "$COMPOSER_AUTH_B64" | base64 -d > "$_composer_home/auth.json"
    chmod 600 "$_composer_home/auth.json"
    echo "[fatturino][15-install-plugins] auth.json written from COMPOSER_AUTH_B64"
elif [ -n "${COMPOSER_AUTH:-}" ]; then
    # Fallback: raw JSON (old format — present on containers deployed before the base64 migration).
    mkdir -p "$_composer_home"
    printf '%s' "$COMPOSER_AUTH" > "$_composer_home/auth.json"
    chmod 600 "$_composer_home/auth.json"
    echo "[fatturino][15-install-plugins] auth.json written from COMPOSER_AUTH (fallback)"
else
    echo "[fatturino][15-install-plugins] WARNING: no Codeberg credentials found — private repos will fail"
fi

# Configure git credential helper with the same Codeberg token Composer uses.
# Required because composer "repositories.X" entries below use type:git, which
# invokes git clone directly. Git does NOT read $COMPOSER_HOME/auth.json — only
# Composer does. Without this, git clone falls through to interactive prompt
# and fails with GIT_TERMINAL_PROMPT=0.
if [ -f "$_composer_home/auth.json" ]; then
    # Extract password from auth.json without depending on jq
    _codeberg_token=$(grep -o '"password"[[:space:]]*:[[:space:]]*"[^"]*"' "$_composer_home/auth.json" \
        | head -1 \
        | sed 's/.*"password"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/')

    if [ -n "$_codeberg_token" ]; then
        # Use the "store" helper writing to a fixed file (default is ~/.git-credentials,
        # but the container's HOME may not be writable — /tmp always is).
        git config --global credential.helper "store --file=/tmp/.git-credentials"
        printf 'https://fatturino:%s@codeberg.org\n' "$_codeberg_token" > /tmp/.git-credentials
        chmod 600 /tmp/.git-credentials
        echo "[fatturino][15-install-plugins] git credentials configured for codeberg.org"
    else
        echo "[fatturino][15-install-plugins] WARNING: could not extract token from auth.json — git clones will prompt"
    fi
    unset _codeberg_token
fi
unset _composer_home

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
