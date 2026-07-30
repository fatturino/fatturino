<?php

namespace App\Console\Commands;

use App\Models\EiInboundLog;
use Illuminate\Console\Command;

class PruneOpenApiWebhookPayloads extends Command
{
    protected $signature = 'openapi:prune-webhook-payloads {--dry-run : Show the number of payloads that would be redacted}';

    protected $description = 'Redact retained OpenAPI webhook payloads after the configured retention period';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) config('fe-openapi.webhook_payload_retention_days'));
        $query = EiInboundLog::query()
            ->whereNotNull('raw_payload')
            ->where('created_at', '<=', $cutoff);

        $count = $query->count();

        if ($this->option('dry-run')) {
            $this->info("{$count} webhook payload(s) would be redacted.");

            return self::SUCCESS;
        }

        $query->update(['raw_payload' => null]);
        $this->info("{$count} webhook payload(s) redacted.");

        return self::SUCCESS;
    }
}
