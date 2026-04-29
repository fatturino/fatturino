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
     *      code is still accessible — lets the plugin clean up settings, data, etc.
     *   2. `composer remove` the package.
     *   3. Delete the `plugins/<name>/` folder (skipped with --keep-files).
     *   4. Remove the row from the `plugins` DB table.
     *   5. Refresh package discovery.
     */
    protected $signature = 'plugin:uninstall
                            {name : The plugin short name (e.g. plugin-cloud)}
                            {--keep-files : Skip deletion of the plugins/<name>/ folder}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Uninstall a Fatturino plugin';

    public function handle(): int
    {
        $name = (string) $this->argument('name');
        $packageName = "fatturino/{$name}";
        $pluginPath = base_path("plugins/{$name}");

        if (! $this->option('force') && ! $this->confirm("Uninstall {$packageName}?")) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $this->info("Uninstalling plugin: {$packageName}");

        // Run the plugin's own uninstall hook before removing its code.
        $uninstallCommand = "{$name}:uninstall";
        if ($this->getApplication()->has($uninstallCommand)) {
            $this->info("Running {$uninstallCommand}...");
            $this->call($uninstallCommand);
        }

        $this->info("Running composer remove {$packageName}...");

        $remove = Process::path(base_path())
            ->run(['composer', 'remove', $packageName, '--no-interaction']);

        if ($remove->failed()) {
            $this->error("composer remove failed:\n".$remove->errorOutput());

            return self::FAILURE;
        }

        if (! $this->option('keep-files') && is_dir($pluginPath)) {
            $this->info("Deleting plugins/{$name}/...");
            Process::path(base_path())->run(['rm', '-rf', $pluginPath]);
        }

        DB::table('plugins')->where('id', $name)->delete();

        $this->call('package:discover');

        $this->info("Plugin {$packageName} uninstalled.");

        return self::SUCCESS;
    }
}
