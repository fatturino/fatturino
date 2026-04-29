<?php

namespace App\Console\Commands;

use App\Services\PluginRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

class PluginInstall extends Command
{
    /**
     * Install or update a Fatturino plugin.
     *
     * If the plugin folder under `plugins/<name>` is missing, the command
     * clones the repo from Codeberg (or the URL given via `--repo`).
     * If it exists, the clone step is skipped and Composer is asked to
     * install/update the local path package.
     */
    protected $signature = 'plugin:install
                            {name : The plugin short name (e.g. plugin-cloud)}
                            {--locked : Mark the plugin as locked (cannot be deactivated from UI)}
                            {--repo= : Override the git URL used to clone the plugin}';

    protected $description = 'Install or update a Fatturino plugin from the local plugins/ folder or from Codeberg';

    public function handle(PluginRegistry $registry): int
    {
        $name = (string) $this->argument('name');
        $pluginPath = base_path("plugins/{$name}");
        $packageName = "fatturino/{$name}";

        $this->info("Installing plugin: {$packageName}");

        if (! is_dir($pluginPath)) {
            $repoUrl = (string) ($this->option('repo') ?: "https://codeberg.org/fatturino/{$name}.git");

            $this->configureCodebergAuth();

            $this->info("Cloning {$repoUrl} into plugins/{$name}/...");

            $clone = Process::path(base_path())
                ->env(['GIT_TERMINAL_PROMPT' => '0'])
                ->run(['git', 'clone', '--depth', '1', $repoUrl, "plugins/{$name}"]);

            if ($clone->failed()) {
                $this->error("git clone failed:\n".$clone->errorOutput());

                return self::FAILURE;
            }
        } else {
            $this->info("Plugin folder already exists at plugins/{$name}/, skipping clone.");
        }

        if (! file_exists($pluginPath.'/composer.json')) {
            $this->error("plugins/{$name}/composer.json not found, cannot install.");

            return self::FAILURE;
        }

        $this->info("Running composer require {$packageName}:@dev...");

        $require = Process::path(base_path())
            ->run(['composer', 'require', "{$packageName}:@dev", '--no-interaction', '--no-scripts']);

        if ($require->failed()) {
            $this->error("composer require failed:\n".$require->errorOutput());

            return self::FAILURE;
        }

        $this->info('Refreshing autoload + package discovery...');

        $dump = Process::path(base_path())->run(['composer', 'dump-autoload', '--optimize']);
        if ($dump->failed()) {
            $this->error("composer dump-autoload failed:\n".$dump->errorOutput());

            return self::FAILURE;
        }

        $this->call('package:discover');

        $now = now();

        DB::table('plugins')->updateOrInsert(
            ['id' => $name],
            [
                'active' => true,
                'locked' => (bool) $this->option('locked'),
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        $registry->setLocked($name, (bool) $this->option('locked'));

        // Run the plugin's own install hook if it provides one (e.g. for seeding).
        // The command is optional: plugins that have nothing to do simply don't define it.
        $installCommand = "{$name}:install";
        if ($this->getApplication()->has($installCommand)) {
            $this->info("Running {$installCommand}...");
            $this->call($installCommand);
        }

        $this->info("Plugin {$packageName} installed (locked: ".($this->option('locked') ? 'yes' : 'no').').');

        return self::SUCCESS;
    }

    /**
     * Configure Composer + git credentials for Codeberg when CODEBERG_TOKEN is set.
     * Mirrors the previous bash entrypoint logic so private repos work end-to-end.
     */
    private function configureCodebergAuth(): void
    {
        $token = env('CODEBERG_TOKEN');
        if (empty($token)) {
            return;
        }

        Process::path(base_path())->run([
            'composer', 'config', '--global', 'http-basic.codeberg.org', 'fatturino', $token,
        ]);

        $credentialsFile = '/tmp/.git-credentials';
        @file_put_contents($credentialsFile, "https://fatturino:{$token}@codeberg.org\n");
        @chmod($credentialsFile, 0600);

        Process::path(base_path())->run([
            'git', 'config', '--global', 'credential.helper', "store --file={$credentialsFile}",
        ]);
    }
}
