<?php

namespace App\Support;

use App\Contracts\HasTimeline;
use App\Models\FiscalDocumentLine;
use Illuminate\Support\Collection;
use OwenIt\Auditing\Models\Audit;

/**
 * Builds a merged timeline of product events and audit events for an invoice.
 * Groups consecutive FiscalDocumentLine audits from the same user within the
 * configured grouping window into a single expandable cluster.
 */
class InvoiceTimelineBuilder
{
    private const GROUPING_WINDOW_SECONDS = 60;

    /**
     * Return clusters for display, sorted newest first.
     *
     * @return array<int, array{key: string, items: array<int, array<string, mixed>>}>
     */
    public function build(HasTimeline $invoice): array
    {
        $entries = $this->loadEntries($invoice)
            ->sortByDesc('at')
            ->values();

        return $this->clusterAdjacentLineAudits($entries);
    }

    private function loadEntries(HasTimeline $invoice): Collection
    {
        $lineIds = $invoice->lines()->pluck('id');

        $rawAudits = Audit::query()
            ->with('user')
            ->where(function ($q) use ($invoice, $lineIds) {
                $q->where(function ($sub) use ($invoice) {
                    $sub->where('auditable_type', get_class($invoice))
                        ->where('auditable_id', $invoice->id);
                });

                if ($lineIds->isNotEmpty()) {
                    $q->orWhere(function ($sub) use ($lineIds) {
                        $sub->where('auditable_type', FiscalDocumentLine::class)
                            ->whereIn('auditable_id', $lineIds);
                    });
                }
            })
            ->get();

        $documentEvents = $invoice->events()->with('creator')->get()->map(fn ($event) => [
            'source' => 'document_event',
            'at' => $event->occurred_at,
            'user_name' => $event->creator?->name,
            'user_id' => $event->created_by,
            'event' => $event->event_type,
            'auditable_type' => null,
            'auditable_id' => null,
            'title' => $event->title,
            'message' => $event->message,
            'status' => $event->status,
            'channel' => $event->channel,
            'recipient_email' => $event->recipient_email,
            'bcc' => $event->bcc,
            'subject' => $event->subject,
            'error_message' => $event->error_message,
        ]);

        $audits = $rawAudits->map(fn ($audit) => [
            'source' => 'audit',
            'at' => $audit->created_at,
            'user_name' => $audit->user?->name,
            'user_id' => $audit->user_id,
            'event' => $audit->event,
            'auditable_type' => $audit->auditable_type,
            'auditable_id' => $audit->auditable_id,
            'old_values' => $audit->old_values,
            'new_values' => $audit->new_values,
        ]);

        return $documentEvents->concat($audits);
    }

    /**
     * @return array<int, array{key: string, items: array<int, array<string, mixed>>}>
     */
    private function clusterAdjacentLineAudits(Collection $entries): array
    {
        $clusters = [];

        foreach ($entries as $entry) {
            $last = end($clusters) ?: null;

            if ($last && $this->canMergeIntoCluster($last, $entry)) {
                $clusters[array_key_last($clusters)]['items'][] = $entry;

                continue;
            }

            $clusters[] = [
                'key' => uniqid('cluster_', true),
                'items' => [$entry],
            ];
        }

        return $clusters;
    }

    private function canMergeIntoCluster(array $cluster, array $entry): bool
    {
        if ($entry['source'] !== 'audit' || $entry['auditable_type'] !== FiscalDocumentLine::class) {
            return false;
        }

        $first = $cluster['items'][0];

        if ($first['source'] !== 'audit' || $first['auditable_type'] !== FiscalDocumentLine::class) {
            return false;
        }

        if ($first['user_id'] !== $entry['user_id']) {
            return false;
        }

        return abs($first['at']->diffInSeconds($entry['at'])) <= self::GROUPING_WINDOW_SECONDS;
    }
}
