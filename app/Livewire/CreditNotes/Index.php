<?php

namespace App\Livewire\CreditNotes;

use App\Enums\InvoiceStatus;
use App\Models\CreditNote;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast;
    use WithPagination;

    public string $search = '';

    public bool $drawer = false;

    public array $sortBy = ['column' => 'date', 'direction' => 'desc'];

    public int $fiscalYear;

    public bool $isReadOnly;

    public string $filterStatus = '';

    public function mount(): void
    {
        $this->fiscalYear = session('fiscal_year', now()->year);
        $this->isReadOnly = $this->fiscalYear < now()->year;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function clear(): void
    {
        $this->reset(['search', 'drawer', 'sortBy', 'filterStatus']);
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
            ['key' => 'number', 'label' => __('app.credit_notes.col_number'), 'class' => 'w-40'],
            ['key' => 'date', 'label' => __('app.credit_notes.col_date'), 'class' => 'w-32'],
            ['key' => 'contact.name', 'label' => __('app.credit_notes.col_customer'), 'sortable' => false],
            ['key' => 'total_gross', 'label' => __('app.credit_notes.col_total'), 'class' => 'w-36 text-right'],
            ['key' => 'status', 'label' => __('app.credit_notes.col_status'), 'class' => 'w-32'],
        ];
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
            ->orderBy(...array_values($this->sortBy))
            ->paginate(10);

        return view('livewire.credit-notes.index', [
            'creditNotes' => $creditNotes,
            'headers' => $this->headers(),
            'fiscalYear' => $this->fiscalYear,
            'isReadOnly' => $this->isReadOnly,
        ]);
    }
}
