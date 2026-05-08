<?php

namespace App\Livewire\SelfInvoices;

use App\Enums\InvoiceStatus;
use App\Models\SelfInvoice;
use Livewire\Component;
use Livewire\WithPagination;
use App\Traits\Toast;

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

    // Reset pagination when filters change
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
        $this->success(__('app.self_invoices.filters_cleared'), position: 'toast-bottom');
    }

    // Summary stats for the current fiscal year
    public function getStatsProperty(): array
    {
        $base = SelfInvoice::query()->whereYear('date', $this->fiscalYear);

        return [
            'total_count' => (clone $base)->count(),
            'total_gross' => (clone $base)->sum('total_gross'),
        ];
    }

    // Filter options for the drawer
    public function getStatusOptionsProperty(): array
    {
        return collect(InvoiceStatus::cases())
            ->map(fn ($s) => ['id' => $s->value, 'name' => $s->label()])
            ->toArray();
    }

    public function delete(SelfInvoice $selfInvoice): void
    {
        // Prevent deletion of invoices already processed by SDI
        if (! $selfInvoice->isSdiEditable()) {
            $this->error(__('app.self_invoices.cannot_delete_sdi'), position: 'toast-bottom');

            return;
        }

        $selfInvoice->delete();
        $this->warning(
            __('app.self_invoices.deleted', ['number' => $selfInvoice->number]),
            __('app.common.goodbye'),
            position: 'toast-bottom'
        );
    }

    public function headers(): array
    {
        return [
            ['key' => 'number', 'label' => __('app.self_invoices.col_number'), 'class' => 'w-40', 'render' => fn($row) => '<span class="font-semibold whitespace-nowrap">' . e($row->number) . '</span>'],
            ['key' => 'document_type', 'label' => __('app.self_invoices.col_document_type'), 'class' => 'w-24', 'render' => fn($row) => '<span class="text-sm">' . e($row->document_type) . '</span>'],
            ['key' => 'date', 'label' => __('app.self_invoices.col_date'), 'class' => 'w-32', 'render' => fn($row) => '<span class="text-sm">' . $row->date->format('d/m/Y') . '</span>'],
            ['key' => 'contact.name', 'label' => __('app.self_invoices.col_supplier'), 'sortable' => false, 'render' => fn($row) => '<span class="font-medium">' . e($row->contact?->name) . '</span>'],
            ['key' => 'total_gross', 'label' => __('app.self_invoices.col_total'), 'class' => 'w-36 text-right', 'render' => fn($row) => '<div class="text-right font-semibold">€ ' . number_format($row->total_gross / 100, 2, ',', '.') . '</div>'],
            ['key' => 'status', 'label' => __('app.self_invoices.col_status'), 'class' => 'w-32', 'view' => 'partials.invoice-status-cell'],
            ['key' => 'actions', 'label' => '', 'class' => 'w-1', 'view' => 'partials.self-invoice-actions'],
        ];
    }

    public function render()
    {
        $invoices = SelfInvoice::query()
            ->with('contact')
            ->whereYear('date', $this->fiscalYear)
            ->when($this->search, fn ($q) => $q->where(
                fn ($q) => $q->where('number', 'like', "%{$this->search}%")
                    ->orWhereHas('contact', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->orderBy(...array_values($this->sortBy))
            ->paginate(10);

        return view('livewire.self-invoices.index', [
            'invoices' => $invoices,
            'headers' => $this->headers(),
            'fiscalYear' => $this->fiscalYear,
            'isReadOnly' => $this->isReadOnly,
        ]);
    }
}
