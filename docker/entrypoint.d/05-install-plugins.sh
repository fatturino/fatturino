#!/bin/sh

# Install plugins specified via FATTURINO_PLUGINS env var.
# Format: space-separated list of plugin names (without "fatturino/" prefix).
# Example: FATTURINO_PLUGINS="plugin-cloud plugin-analytics"
#
# Naming convention:
#   plugin name    → plugin-cloud
#   git repo       → https://codeberg.org/fatturino/plugin-cloud.git
#   composer pkg   → fatturino/plugin-cloud  (same as plugin name)
#
# Private repositories:
#   Set COMPOSER_AUTH with Codeberg credentials (API token recommended).
#   Composer reads this variable automatically before any VCS request.
#   Example:
#     COMPOSER_AUTH='{"http-basic":{"codeberg.org":{"username":"user","password":"api-token"}}}'
#   Generate a token at: Codeberg → Settings → Applications → Access Tokens (scope: read:repository)

[ -z "$FATTURINO_PLUGINS" ] && exit 0

WORKDIR="/var/www/html"

# Extract Codeberg credentials from COMPOSER_AUTH to embed them in the clone URL.
# Composer does not forward http-basic auth to git subprocesses for type:git repos,
# so we embed credentials directly in the HTTPS URL to avoid interactive prompts.
_cb_user=""
_cb_token=""
if [ -n "$COMPOSER_AUTH" ]; then
    _cb_user=$(echo "$COMPOSER_AUTH" | php -r "
        \$a = json_decode(file_get_contents('php://stdin'), true);
        echo \$a['http-basic']['codeberg.org']['username'] ?? '';
    ")
    _cb_token=$(echo "$COMPOSER_AUTH" | php -r "
        \$a = json_decode(file_get_contents('php://stdin'), true);
        echo \$a['http-basic']['codeberg.org']['password'] ?? '';
    ")
fi

if [ -n "$_cb_user" ] && [ -n "$_cb_token" ]; then
    _codeberg_prefix="https://${_cb_user}:${_cb_token}@codeberg.org"
else
    _codeberg_prefix="https://codeberg.org"
fi

# Pre-flight check: verify each plugin repo is reachable via Codeberg API
# before touching composer. Turns the opaque "git clone failed" error from
# Composer into an actionable message pointing at the real cause (token
# scope, collaborator status, or typos in the plugin name).
echo "[fatturino] Pre-flight check for plugins: $FATTURINO_PLUGINS"
for plugin in $FATTURINO_PLUGINS; do
    api_url="https://codeberg.org/api/v1/repos/fatturino/${plugin}"

    if [ -n "$_cb_user" ] && [ -n "$_cb_token" ]; then
        status=$(curl -s -o /dev/null -w "%{http_code}" -u "${_cb_user}:${_cb_token}" "$api_url")
    else
        status=$(curl -s -o /dev/null -w "%{http_code}" "$api_url")
    fi

    case "$status" in
        200)
            echo "[fatturino] OK: fatturino/${plugin} reachable (HTTP 200)"
            ;;
        401)
            echo "[fatturino] ERROR: Codeberg returned 401 for fatturino/${plugin} — COMPOSER_AUTH token is invalid or expired." >&2
            exit 1
            ;;
        403)
            echo "[fatturino] ERROR: Codeberg returned 403 for fatturino/${plugin} — token user lacks access. Check token scope (read:repository) and collaborator status on the repo." >&2
            exit 1
            ;;
        404)
            echo "[fatturino] ERROR: Codeberg returned 404 for fatturino/${plugin} — repo not found or token user has no visibility on it (private repo requires collaborator access)." >&2
            exit 1
            ;;
        *)
            echo "[fatturino] ERROR: Codeberg returned HTTP $status for fatturino/${plugin} — aborting plugin installation." >&2
            exit 1
            ;;
    esac
done

echo "[fatturino] Installing plugins: $FATTURINO_PLUGINS"
cd "$WORKDIR"

for plugin in $FATTURINO_PLUGINS; do
    repo="${_codeberg_prefix}/fatturino/${plugin}.git"
    package="fatturino/${plugin}"

    # Use type "git" (not "vcs") to bypass Composer's Gitea API driver.
    # The Gitea driver fetches the API and uses ssh_url from the response,
    # causing "cannot run ssh: No such file or directory" in containers without openssh.
    # type "git" forces Composer to clone directly from the given HTTPS URL.
    composer config "repositories.${plugin}" '{"type":"git","url":"'"$repo"'"}' --quiet
    echo "[fatturino] Installing $package..."
    # --no-scripts: skip package:discover during each install, run once at the end
    composer require "${package}:dev-main" --no-interaction --no-scripts
done

unset _cb_user _cb_token _codeberg_prefix

composer dump-autoload --optimize
php artisan package:discover --ansi

# Rebuild frontend assets so Tailwind scans plugin Blade views
echo "[fatturino] Rebuilding frontend assets..."
bun run build
php artisan optimize:clear

echo "[fatturino] Plugins installed"
