<?php

namespace App\Livewire;

use App\Models\Invoice;
use Carbon\Carbon;
use Livewire\Component;

class FiscalYearSelector extends Component
{
    public int $selectedYear;

    // Captured during mount() (initial GET request) when url()->current() is the actual page URL.
    // Inside Livewire update requests, url()->current() returns the internal Livewire endpoint,
    // so we must store the real URL at mount time and reuse it for redirects.
    public string $pageUrl = '';

    public function mount(): void
    {
        $this->selectedYear = session('fiscal_year', now()->year);
        $this->pageUrl      = url()->current();
    }

    /**
     * Called automatically by Livewire when $selectedYear is updated via wire:model.live.
     * The value comes in as a string from the select element, so we cast it to int.
     */
    public function updatedSelectedYear(string $year): void
    {
        $year = (int) $year;

        session(['fiscal_year' => $year]);
        $this->selectedYear = $year;

        // Redirect back to the captured page URL (not url()->current() which would resolve
        // to the internal Livewire update endpoint during this request)
        $this->redirect($this->pageUrl, navigate: true);
    }

    /**
     * Returns available fiscal years in descending order.
     * Always includes the current year, plus any year that has invoices.
     */
    public function availableYears(): array
    {
        $minDate = Invoice::withoutGlobalScopes()->min('date');
        $minYear = $minDate ? (int) Carbon::parse($minDate)->year : now()->year;
        $currentYear = now()->year;

        return range($currentYear, $minYear);
    }

    public function render()
    {
        return view('livewire.fiscal-year-selector', [
            'availableYears' => $this->availableYears(),
            'currentYear'    => now()->year,
        ]);
    }
}
