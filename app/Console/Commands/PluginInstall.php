<?php

namespace App\Console\Commands;

use App\Services\PluginRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

class PluginInstall extends Command
{
    /**
     * Install a Fatturino plugin.
     *
     * Clones the plugin repo into plugins/<name>/ (if not already present) and
     * registers it in the plugins DB table. Autoloading and service providers are
     * handled dynamically by AppServiceProvider at boot — no modifications to
     * composer.json or bootstrap/providers.php are needed.
     *
     * The plugin's own {name}:install hook (if defined) is called in a subprocess
     * so that the newly cloned plugin is fully loaded before the hook runs.
     */
    protected $signature = 'plugin:install
                            {name : The plugin short name (e.g. plugin-cloud)}
                            {--locked : Mark the plugin as locked (cannot be deactivated from UI)}
                            {--repo= : Override the git URL used to clone the plugin}';

    protected $description = 'Install a Fatturino plugin from the local plugins/ folder or from Codeberg';

    public function handle(PluginRegistry $registry): int
    {
        $name = (string) $this->argument('name');
        $pluginPath = base_path("plugins/{$name}");

        $this->info("Installing plugin: fatturino/{$name}");

        if (! is_dir($pluginPath)) {
            // Use SSH in local dev (no token), HTTPS in CI/Docker (token present).
            $hasToken = $this->configureCodebergAuth();
            $defaultRepo = $hasToken
                ? "https://codeberg.org/fatturino/{$name}.git"
                : "git@codeberg.org:fatturino/{$name}.git";
            $repoUrl = (string) ($this->option('repo') ?: $defaultRepo);

            $this->info("Cloning {$repoUrl} into plugins/{$name}/...");

            $process = Process::path(base_path());
            if ($hasToken) {
                $process = $process->env(['GIT_TERMINAL_PROMPT' => '0']);
            }

            $clone = $process->run(['git', 'clone', '--depth', '1', $repoUrl, "plugins/{$name}"]);

            if ($clone->failed()) {
                $this->error("git clone failed:\n".$clone->errorOutput());

                return self::FAILURE;
            }
        } else {
            $this->info("Plugin folder already exists at plugins/{$name}/, skipping clone.");
        }

        if (! file_exists($pluginPath.'/composer.json')) {
            $this->error("plugins/{$name}/composer.json not found.");

            return self::FAILURE;
        }

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

        // Run the plugin's own install hook in a subprocess so the newly cloned
        // plugin is fully loaded (AppServiceProvider scans plugins/ at boot).
        $installCommand = "{$name}:install";
        $hook = Process::path(base_path())
            ->run(['php', 'artisan', $installCommand, '--no-interaction']);

        if ($hook->failed()) {
            // A non-zero exit means either the command doesn't exist (artisan exits 1
            // with "Command not found") or the hook itself failed. Only treat it as an
            // error when it produced output that looks like a real failure.
            if (str_contains($hook->errorOutput(), 'not defined') || str_contains($hook->output(), 'not defined')) {
                $this->line("No {$installCommand} hook found, skipping.");
            } else {
                $this->error("Install hook failed:\n".$hook->output().$hook->errorOutput());

                return self::FAILURE;
            }
        } else {
            $this->output->write($hook->output());
        }

        $this->info("Plugin fatturino/{$name} installed (locked: ".($this->option('locked') ? 'yes' : 'no').').');

        return self::SUCCESS;
    }

    /**
     * Configure Composer + git credentials for Codeberg when CODEBERG_TOKEN is set.
     * Returns true if a token was found and credentials were configured.
     */
    private function configureCodebergAuth(): bool
    {
        $token = env('CODEBERG_TOKEN');
        if (empty($token)) {
            return false;
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

        return true;
    }
}
