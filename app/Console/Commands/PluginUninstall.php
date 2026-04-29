<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

class PluginUninstall extends Command
{
    /**
     * Uninstall a Fatturino plugin.
     *
     * Order of operations:
     *   1. Run the plugin's own `{name}:uninstall` hook (if defined) while its
     *      code is still loaded in the current process.
     *   2. Delete the plugins/<name>/ folder (skipped with --keep-files).
     *   3. Remove the row from the plugins DB table.
     *
     * Autoloading and provider registration are handled dynamically at boot by
     * AppServiceProvider — removing the folder is enough to stop loading the plugin.
     */
    protected $signature = 'plugin:uninstall
                            {name : The plugin short name (e.g. plugin-cloud)}
                            {--keep-files : Skip deletion of the plugins/<name>/ folder}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Uninstall a Fatturino plugin';

    public function handle(): int
    {
        $name = (string) $this->argument('name');
        $pluginPath = base_path("plugins/{$name}");

        if (! $this->option('force') && ! $this->confirm("Uninstall fatturino/{$name}?")) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $this->info("Uninstalling plugin: fatturino/{$name}");

        // Run the plugin's own uninstall hook while its code is still loaded.
        $uninstallCommand = "{$name}:uninstall";
        if ($this->getApplication()->has($uninstallCommand)) {
            $this->info("Running {$uninstallCommand}...");
            $this->call($uninstallCommand);
        }

        if (! $this->option('keep-files') && is_dir($pluginPath)) {
            $this->info("Deleting plugins/{$name}/...");
            Process::path(base_path())->run(['rm', '-rf', $pluginPath]);
        }

        DB::table('plugins')->where('id', $name)->delete();

        $this->info("Plugin fatturino/{$name} uninstalled.");

        return self::SUCCESS;
    }
}
