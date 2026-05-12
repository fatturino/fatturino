<?php

namespace App\Livewire\Invoices;

use App\Contracts\HasTimeline;
use App\Support\InvoiceTimelineBuilder;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Renders a merged timeline of audit events and SDI logs for an invoice.
 * Lazy-loaded so audits are only fetched when the history tab is opened.
 */
#[Lazy]
class InvoiceTimeline extends Component
{
    public HasTimeline $invoice;

    /** @var array<string, bool> */
    public array $expanded = [];

    public function toggleCluster(string $clusterKey): void
    {
        $this->expanded[$clusterKey] = ! ($this->expanded[$clusterKey] ?? false);
    }

    public function render(InvoiceTimelineBuilder $builder)
    {
        return view('livewire.invoices.invoice-timeline', [
            'clusters' => $builder->build($this->invoice),
        ]);
    }
}
