#!/bin/sh

# Install plugins listed in FATTURINO_PLUGINS (space-separated, no "fatturino/" prefix).
# Cloud-side ProvisionTenant already validates COMPOSER_AUTH against Codeberg before deploy,
# so this script assumes credentials are correct and focuses on the install itself.
#
# Naming: plugin-cloud  →  https://codeberg.org/fatturino/plugin-cloud.git  →  fatturino/plugin-cloud
#
# Private repos require COMPOSER_AUTH:
#   {"http-basic":{"codeberg.org":{"username":"user","password":"api-token"}}}

[ -z "$FATTURINO_PLUGINS" ] && exit 0

cd /var/www/html

# Build the Codeberg URL prefix, embedding credentials when available.
# Composer does not forward COMPOSER_AUTH to git subprocesses for type:git repos,
# so credentials must live in the clone URL to avoid interactive prompts.
_codeberg_prefix=$(echo "${COMPOSER_AUTH:-}" | php -r "
    \$a = json_decode(file_get_contents('php://stdin'), true);
    \$u = \$a['http-basic']['codeberg.org']['username'] ?? '';
    \$p = \$a['http-basic']['codeberg.org']['password'] ?? '';
    echo (\$u && \$p) ? \"https://{\$u}:{\$p}@codeberg.org\" : 'https://codeberg.org';
")

echo "[fatturino] Installing plugins: $FATTURINO_PLUGINS"
for plugin in $FATTURINO_PLUGINS; do
    # type:git (not vcs) bypasses Composer's Gitea driver, which would otherwise
    # resolve ssh_url from the API and fail — containers have no openssh client.
    composer config "repositories.${plugin}" \
        '{"type":"git","url":"'"${_codeberg_prefix}/fatturino/${plugin}.git"'"}' --quiet
    composer require "fatturino/${plugin}:dev-main" --no-interaction --no-scripts
done

unset _codeberg_prefix

composer dump-autoload --optimize
php artisan package:discover --ansi

# Rebuild frontend assets so Tailwind scans plugin Blade views
bun run build
php artisan optimize:clear

echo "[fatturino] Plugins installed"
