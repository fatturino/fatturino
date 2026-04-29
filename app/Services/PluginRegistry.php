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
 * Activation and lock state are persisted in the `plugins` DB table:
 * - `active`  controls whether the plugin should boot
 * - `locked`  controls whether the UI can deactivate it
 *
 * The lock flag is set at install time (e.g. via `plugin:install --locked`),
 * not declared by the plugin itself.
 */
class PluginRegistry
{
    /** @var array<string, array{name: string, description: string, version: string, author: string, active: bool, locked: bool}> */
    private array $plugins = [];

    /** @var array<string, string[]> Blade view names keyed by injection slot */
    private array $injections = [];

    /** @var array<string, array{active: bool, locked: bool}>|null Cached DB state (null = not loaded yet) */
    private ?array $dbState = null;

    /**
     * Register a plugin. Called by plugin ServiceProviders during boot().
     *
     * Returns true if the plugin is active and should continue booting.
     * Returns false if deactivated: the plugin should stop and not register
     * any functionality (routes, menu items, bindings, etc.).
     */
    public function register(string $id, string $name, string $description = '', string $version = '1.0.0', string $author = ''): bool
    {
        $state = $this->resolvePluginState($id);

        $this->plugins[$id] = [
            'name' => $name,
            'description' => $description,
            'version' => $version,
            'author' => $author,
            'active' => $state['active'],
            'locked' => $state['locked'],
        ];

        return $state['active'];
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
        return $this->resolvePluginState($id)['active'];
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
     * Set or clear the lock flag on a plugin. Used by the install command.
     */
    public function setLocked(string $id, bool $locked): void
    {
        DB::table('plugins')->updateOrInsert(
            ['id' => $id],
            ['locked' => $locked, 'updated_at' => now()],
        );

        if (isset($this->plugins[$id])) {
            $this->plugins[$id]['locked'] = $locked;
        }

        $this->dbState = null;
    }

    /**
     * Resolve a plugin's full state (active + locked) from the DB.
     * First-time plugins default to active=true, locked=false and are inserted.
     *
     * @return array{active: bool, locked: bool}
     */
    private function resolvePluginState(string $id): array
    {
        $state = $this->loadDbState();

        if (array_key_exists($id, $state)) {
            return $state[$id];
        }

        $this->persistNewPlugin($id);

        return ['active' => true, 'locked' => false];
    }

    /**
     * Load all plugin states from DB (cached per request).
     *
     * @return array<string, array{active: bool, locked: bool}>
     */
    private function loadDbState(): array
    {
        if ($this->dbState !== null) {
            return $this->dbState;
        }

        // During migrations or before the table exists, treat all plugins as active and unlocked
        if (! $this->tableExists()) {
            $this->dbState = [];

            return $this->dbState;
        }

        // The `locked` column was added in a later migration; gracefully degrade
        // when the boot path runs before that migration has been applied.
        $hasLockedColumn = Schema::hasColumn('plugins', 'locked');

        $columns = $hasLockedColumn ? ['id', 'active', 'locked'] : ['id', 'active'];
        $rows = DB::table('plugins')->get($columns);

        $this->dbState = [];
        foreach ($rows as $row) {
            $this->dbState[$row->id] = [
                'active' => (bool) $row->active,
                'locked' => $hasLockedColumn ? (bool) $row->locked : false,
            ];
        }

        return $this->dbState;
    }

    /**
     * Insert a new plugin as active and unlocked in the DB.
     */
    private function persistNewPlugin(string $id): void
    {
        if (! $this->tableExists()) {
            return;
        }

        $now = now();

        $payload = [
            'id' => $id,
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('plugins', 'locked')) {
            $payload['locked'] = false;
        }

        DB::table('plugins')->insertOrIgnore($payload);

        if ($this->dbState !== null) {
            $this->dbState[$id] = ['active' => true, 'locked' => false];
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
