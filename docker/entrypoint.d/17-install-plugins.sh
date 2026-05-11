#!/bin/sh
set -eu

# Install plugins listed in FATTURINO_PLUGINS (space-separated, no "fatturino/" prefix).
# Each plugin is delegated to `php artisan plugin:install`, which clones from
# Codeberg into plugins/<name>/ on first run and registers the plugin in the
# `plugins` DB table. CODEBERG_TOKEN must be set for private repositories.
#
# Naming: plugin-cloud  ->  https://codeberg.org/fatturino/plugin-cloud.git  ->  fatturino/plugin-cloud

echo "[fatturino][17-install-plugins] start"

if [ -z "${FATTURINO_PLUGINS:-}" ]; then
    echo "[fatturino][17-install-plugins] FATTURINO_PLUGINS empty, skipping"
    exit 0
fi

echo "[fatturino][17-install-plugins] CODEBERG_TOKEN set: $([ -n "${CODEBERG_TOKEN:-}" ] && echo YES || echo NO)"

cd /var/www/html

for plugin in $FATTURINO_PLUGINS; do
    echo "[fatturino][17-install-plugins] installing ${plugin}..."

    pluginPath="/var/www/html/plugins/${plugin}"
    if [ -d "$pluginPath/.git" ]; then
        echo "[fatturino][17-install-plugins] pulling latest changes for ${plugin}..."
        git -C "$pluginPath" pull origin main
    fi

    php artisan plugin:install "$plugin" --no-interaction
done

# Rebuild frontend assets so Tailwind scans plugin Blade views.
bun run build

# Run migrations again now that plugins are installed — plugin migrations live
# inside plugins/ which doesn't exist when 15-migrate.sh runs.
echo "[fatturino][17-install-plugins] running plugin migrations..."
php /var/www/html/artisan migrate --force --no-interaction

echo "[fatturino][17-install-plugins] done"
