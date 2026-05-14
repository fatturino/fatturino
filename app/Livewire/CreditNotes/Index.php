<?php

namespace App\Livewire\CreditNotes;

use App\Enums\InvoiceStatus;
use App\Models\CreditNote;
use App\Traits\Toast;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use Toast;
    use WithPagination;

    public string $search = '';

    public bool $drawer = false;

    public array $sortBy = ['column' => 'number', 'direction' => 'desc'];

    public int $fiscalYear;

    public bool $isReadOnly;

    public string $filterStatus = '';

    public array $selectedIds = [];

    public function mount(): void
    {
        $this->fiscalYear = session('fiscal_year', now()->year);
        $this->isReadOnly = $this->fiscalYear < now()->year;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->selectedIds = [];
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
        $this->selectedIds = [];
    }

    public function clear(): void
    {
        $this->reset(['search', 'drawer', 'sortBy', 'filterStatus', 'selectedIds']);
        $this->resetPage();
        $this->success(__('app.credit_notes.filters_cleared'), position: 'toast-bottom');
    }

    public function getStatsProperty(): array
    {
        $base = CreditNote::query()->whereYear('date', $this->fiscalYear);

        return [
            'total_count' => (clone $base)->count(),
            'total_gross' => (clone $base)->sum('total_gross'),
        ];
    }

    public function getStatusOptionsProperty(): array
    {
        return collect(InvoiceStatus::cases())
            ->map(fn ($s) => ['id' => $s->value, 'name' => $s->label()])
            ->toArray();
    }

    public function delete(CreditNote $creditNote): void
    {
        if (! $creditNote->isSdiEditable()) {
            $this->error(__('app.credit_notes.cannot_delete_sdi'), position: 'toast-bottom');

            return;
        }

        $creditNote->delete();
        $this->warning(
            __('app.credit_notes.deleted', ['number' => $creditNote->number]),
            __('app.common.goodbye'),
            position: 'toast-bottom'
        );
    }

    public function headers(): array
    {
        return [
            ['key' => 'number', 'label' => __('app.credit_notes.col_number'), 'class' => 'w-40', 'render' => fn ($row) => '<span class="font-semibold whitespace-nowrap">'.e($row->number).'</span>'],
            ['key' => 'date', 'label' => __('app.credit_notes.col_date'), 'class' => 'w-32', 'render' => fn ($row) => '<span class="text-sm">'.$row->date->format('d/m/Y').'</span>'],
            ['key' => 'contact.name', 'label' => __('app.invoices.col_customer'), 'sortable' => false, 'render' => fn ($row) => '<span class="font-medium">'.e($row->contact?->name).'</span>'],
            ['key' => 'total_gross', 'label' => __('app.credit_notes.col_total'), 'class' => 'w-36 text-right', 'render' => fn ($row) => '<div class="text-right font-semibold">€ '.number_format($row->total_gross / 100, 2, ',', '.').'</div>'],
            ['key' => 'status', 'label' => __('app.credit_notes.col_status'), 'class' => 'w-32', 'view' => 'partials.invoice-status-cell'],
            ['key' => 'actions', 'label' => '', 'class' => 'w-1', 'view' => 'partials.credit-note-actions'],
        ];
    }

    // Bulk selection

    public function getSelectedCountProperty(): int
    {
        return count($this->selectedIds);
    }

    public function clearSelection(): void
    {
        $this->selectedIds = [];
    }

    public function render()
    {
        $creditNotes = CreditNote::query()
            ->with('contact')
            ->whereYear('date', $this->fiscalYear)
            ->when($this->search, fn ($q) => $q->where(
                fn ($q) => $q->where('number', 'like', "%{$this->search}%")
                    ->orWhereHas('contact', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->orderBy(
                $this->sortBy['column'] === 'number' ? 'sequential_number' : $this->sortBy['column'],
                $this->sortBy['direction']
            )
            ->paginate(10);

        return view('livewire.credit-notes.index', [
            'creditNotes' => $creditNotes,
            'headers' => $this->headers(),
            'fiscalYear' => $this->fiscalYear,
            'isReadOnly' => $this->isReadOnly,
        ]);
    }
}
