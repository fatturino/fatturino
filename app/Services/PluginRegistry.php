<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Central registry for plugins.
 *
 * Plugins call register() in their ServiceProvider boot() to declare themselves.
 * The return value tells the plugin whether it should continue booting.
 *
 * Activation state is persisted in the `plugins` DB table. A plugin that has
 * never been seen defaults to active (opt-out model).
 */
class PluginRegistry
{
    /** @var array<string, array{name: string, description: string, version: string, author: string, active: bool, locked: bool}> */
    private array $plugins = [];

    /** @var array<string, string[]> Blade view names keyed by injection slot */
    private array $injections = [];

    /** @var array<string, bool>|null Cached DB state (null = not loaded yet) */
    private ?array $dbState = null;

    /**
     * Register a plugin. Called by plugin ServiceProviders during boot().
     *
     * Returns true if the plugin is active and should continue booting
     * (register routes, menu items, etc.). Returns false if deactivated:
     * the plugin should stop and not register any functionality.
     */
    /**
     * Register a plugin. Called by plugin ServiceProviders during boot().
     *
     * Returns true if the plugin is active and should continue booting.
     * Returns false if deactivated: the plugin should stop.
     *
     * @param bool $locked If true, the plugin cannot be deactivated from the UI
     */
    public function register(string $id, string $name, string $description = '', string $version = '1.0.0', string $author = '', bool $locked = false): bool
    {
        $active = $this->resolveActiveState($id);

        $this->plugins[$id] = [
            'name' => $name,
            'description' => $description,
            'version' => $version,
            'author' => $author,
            'active' => $active,
            'locked' => $locked,
        ];

        return $active;
    }

    /**
     * Get all registered plugins (both active and inactive).
     *
     * @return array<string, array{name: string, description: string, version: string, author: string, active: bool, locked: bool}>
     */
    public function all(): array
    {
        return $this->plugins;
    }

    /**
     * Check if a specific plugin is installed and active.
     */
    public function has(string $id): bool
    {
        return isset($this->plugins[$id]) && $this->plugins[$id]['active'];
    }

    /**
     * Get the count of active plugins.
     */
    public function count(): int
    {
        return count(array_filter($this->plugins, fn (array $p) => $p['active']));
    }

    /**
     * Register a Blade view to be injected at a named slot in the layout.
     *
     * Slots: 'head-scripts', 'content-before', 'login-before-form'
     */
    public function inject(string $slot, string $view): void
    {
        $this->injections[$slot][] = $view;
    }

    /**
     * Get all registered views for a given injection slot.
     *
     * @return string[]
     */
    public function injections(string $slot): array
    {
        return $this->injections[$slot] ?? [];
    }

    /**
     * Check if a plugin is active in the DB without registering it.
     * Useful in ServiceProvider register() to guard bindings.
     */
    public function isActive(string $id): bool
    {
        return $this->resolveActiveState($id);
    }

    /**
     * Activate a plugin by ID.
     */
    public function activate(string $id): void
    {
        DB::table('plugins')->updateOrInsert(
            ['id' => $id],
            ['active' => true, 'updated_at' => now()],
        );

        if (isset($this->plugins[$id])) {
            $this->plugins[$id]['active'] = true;
        }

        // Invalidate cache so next request re-reads DB
        $this->dbState = null;
    }

    /**
     * Deactivate a plugin by ID. Locked plugins cannot be deactivated.
     */
    public function deactivate(string $id): void
    {
        if (isset($this->plugins[$id]) && $this->plugins[$id]['locked']) {
            return;
        }

        DB::table('plugins')->updateOrInsert(
            ['id' => $id],
            ['active' => false, 'updated_at' => now()],
        );

        if (isset($this->plugins[$id])) {
            $this->plugins[$id]['active'] = false;
        }

        $this->dbState = null;
    }

    /**
     * Resolve a plugin's active state from the DB.
     * First-time plugins default to active and are inserted into the table.
     */
    private function resolveActiveState(string $id): bool
    {
        $state = $this->loadDbState();

        // Plugin already in DB: use stored state
        if (array_key_exists($id, $state)) {
            return $state[$id];
        }

        // First time seeing this plugin: insert as active
        $this->persistNewPlugin($id);

        return true;
    }

    /**
     * Load all plugin states from DB (cached per request).
     *
     * @return array<string, bool>
     */
    private function loadDbState(): array
    {
        if ($this->dbState !== null) {
            return $this->dbState;
        }

        // During migrations or before the table exists, treat all plugins as active
        if (! $this->tableExists()) {
            $this->dbState = [];

            return $this->dbState;
        }

        $this->dbState = DB::table('plugins')
            ->pluck('active', 'id')
            ->map(fn ($active) => (bool) $active)
            ->toArray();

        return $this->dbState;
    }

    /**
     * Insert a new plugin as active in the DB.
     */
    private function persistNewPlugin(string $id): void
    {
        if (! $this->tableExists()) {
            return;
        }

        $now = now();

        DB::table('plugins')->insertOrIgnore([
            'id' => $id,
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Update cache
        if ($this->dbState !== null) {
            $this->dbState[$id] = true;
        }
    }

    private function tableExists(): bool
    {
        try {
            return Schema::hasTable('plugins');
        } catch (\Throwable) {
            // Database may not exist yet (e.g. during Docker build)
            return false;
        }
    }
}
