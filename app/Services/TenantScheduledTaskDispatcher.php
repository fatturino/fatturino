<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\RunTenantScheduledTask;
use App\Models\Tenant;
use LogicException;

final class TenantScheduledTaskDispatcher
{
    public function dispatch(string $task): void
    {
        if (! in_array($task, RunTenantScheduledTask::tasks(), true)) {
            throw new LogicException("Unknown tenant scheduled task [{$task}].");
        }

        Tenant::query()->cursor()->each(function (Tenant $tenant) use ($task): void {
            $tenant->run(function () use ($task, $tenant): void {
                if ($this->isEligibleForCurrentTenant($task)) {
                    RunTenantScheduledTask::dispatch($task, (string) $tenant->getTenantKey());
                }
            });
        });
    }

    private function isEligibleForCurrentTenant(string $task): bool
    {
        return ($task === RunTenantScheduledTask::DEMO_REFRESH) === (bool) config('demo.enabled');
    }
}
