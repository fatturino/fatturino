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

# Force git to use HTTPS instead of SSH for Codeberg.
# Composer's Gitea VCS driver resolves the SSH clone URL from the API response
# even when the configured repository URL is HTTPS — this rewrite prevents
# "cannot run ssh: No such file or directory" failures in containers without openssh.
git config --global url."https://codeberg.org/".insteadOf "git@codeberg.org:"

echo "[fatturino] Installing plugins: $FATTURINO_PLUGINS"
cd "$WORKDIR"

for plugin in $FATTURINO_PLUGINS; do
    repo="https://codeberg.org/fatturino/${plugin}.git"
    package="fatturino/${plugin}"

    composer config "repositories.${plugin}" vcs "$repo" --quiet
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
