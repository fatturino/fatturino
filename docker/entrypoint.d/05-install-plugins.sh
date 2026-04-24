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
_codeberg_prefix="https://codeberg.org"
if [ -n "$COMPOSER_AUTH" ]; then
    _cb_user=$(echo "$COMPOSER_AUTH" | php -r "
        \$a = json_decode(file_get_contents('php://stdin'), true);
        echo \$a['http-basic']['codeberg.org']['username'] ?? '';
    ")
    _cb_token=$(echo "$COMPOSER_AUTH" | php -r "
        \$a = json_decode(file_get_contents('php://stdin'), true);
        echo \$a['http-basic']['codeberg.org']['password'] ?? '';
    ")

    if [ -n "$_cb_user" ] && [ -n "$_cb_token" ]; then
        _codeberg_prefix="https://${_cb_user}:${_cb_token}@codeberg.org"
    fi

    unset _cb_user _cb_token
fi

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

composer dump-autoload --optimize
php artisan package:discover --ansi

# Rebuild frontend assets so Tailwind scans plugin Blade views
echo "[fatturino] Rebuilding frontend assets..."
bun run build
php artisan optimize:clear

echo "[fatturino] Plugins installed"
