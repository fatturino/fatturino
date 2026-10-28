<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use LogicException;
use RuntimeException;

final class RunTenantScheduledTask implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    public const DEMO_REFRESH = 'demo-refresh';

    public const OPENAPI_RECONCILE = 'openapi-reconcile';

    public const OPENAPI_PRUNE_WEBHOOK_PAYLOADS = 'openapi-prune-webhook-payloads';

    public const BACKUP_RUN = 'backup-run';

    public const BACKUP_CLEAN = 'backup-clean';

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly string $task,
        public readonly string $tenantId,
    ) {
        if (! in_array($task, self::tasks(), true)) {
            throw new LogicException("Unknown tenant scheduled task [{$task}].");
        }
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping("tenant-scheduled-task:{$this->tenantId}:{$this->task}"))->expireAfter(3600)->releaseAfter(60)];
    }

    public function handle(): void
    {
        $this->ensureTenantContext();

        if ($this->isDemoTask() !== (bool) config('demo.enabled')) {
            return;
        }

        $exitCode = match ($this->task) {
            self::DEMO_REFRESH => Artisan::call('demo:refresh'),
            self::OPENAPI_RECONCILE => Artisan::call('openapi:reconcile'),
            self::OPENAPI_PRUNE_WEBHOOK_PAYLOADS => Artisan::call('openapi:prune-webhook-payloads'),
            self::BACKUP_RUN => Artisan::call('backup:run', ['--config' => 'backup', '--disable-notifications' => true]),
            self::BACKUP_CLEAN => Artisan::call('backup:clean', ['--config' => 'backup', '--disable-notifications' => true]),
        };

        if ($exitCode !== 0) {
            throw new RuntimeException("Tenant scheduled task [{$this->task}] failed with exit code [{$exitCode}].");
        }
    }

    /** @return list<string> */
    public static function tasks(): array
    {
        return [
            self::DEMO_REFRESH,
            self::OPENAPI_RECONCILE,
            self::OPENAPI_PRUNE_WEBHOOK_PAYLOADS,
            self::BACKUP_RUN,
            self::BACKUP_CLEAN,
        ];
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || (string) tenant('id') !== $this->tenantId) {
            throw new LogicException('Tenant scheduled tasks must run in their queued tenant context.');
        }
    }

    private function isDemoTask(): bool
    {
        return $this->task === self::DEMO_REFRESH;
    }
}
