<?php

namespace App\Livewire;

use App\Models\Invoice;
use Livewire\Component;

/**
 * Small pollable component that shows a badge with the count of
 * pending/unsynced purchase invoices on the sidebar menu.
 */
class PurchaseInvoiceBadge extends Component
{
    public int $count = 0;

    public function mount(): void
    {
        $this->count = $this->queryCount();
    }

    public function render()
    {
        $this->count = $this->queryCount();

        return <<<'BLADE'
            @if($count > 0)
                <span {{ $attributes->merge(['class' => 'inline-flex items-center justify-center w-5 h-5 text-[10px] font-bold text-white bg-warning rounded-full']) }}>
                    {{ $count }}
                </span>
            @endif
        BLADE;
    }

    private function queryCount(): int
    {
        return Invoice::where('type', 'purchase')
            ->whereNull('sdi_uuid')
            ->count();
    }
}
