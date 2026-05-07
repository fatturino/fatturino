<?php

namespace App\Livewire\PurchaseInvoices;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\PurchaseInvoice;
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

    // Filters
    public string $filterStatus = '';

    public string $filterPayment = '';

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

    public function updatedFilterPayment(): void
    {
        $this->resetPage();
    }

    public function clear(): void
    {
        $this->reset(['search', 'drawer', 'sortBy', 'filterStatus', 'filterPayment']);
        $this->resetPage();
        $this->success(__('app.purchase_invoices.filters_cleared'), position: 'toast-bottom');
    }

    // Summary stats for the current fiscal year
    public function getStatsProperty(): array
    {
        $base = PurchaseInvoice::query()->whereYear('date', $this->fiscalYear);

        $totalCount = (clone $base)->count();
        $totalGross = (clone $base)->sum('total_gross');
        $unpaidCount = (clone $base)->where('payment_status', PaymentStatus::Unpaid)->count();
        $unpaidAmount = (clone $base)->where('payment_status', PaymentStatus::Unpaid)->sum('total_gross');
        $overdueCount = (clone $base)->where('payment_status', PaymentStatus::Overdue)->count();

        return [
            'total_count' => $totalCount,
            'total_gross' => $totalGross,
            'unpaid_count' => $unpaidCount,
            'unpaid_amount' => $unpaidAmount,
            'overdue_count' => $overdueCount,
        ];
    }

    // Filter options for the drawer
    public function getStatusOptionsProperty(): array
    {
        return collect(InvoiceStatus::cases())
            ->map(fn ($s) => ['id' => $s->value, 'name' => $s->label()])
            ->toArray();
    }

    public function getPaymentOptionsProperty(): array
    {
        return collect(PaymentStatus::cases())
            ->map(fn ($s) => ['id' => $s->value, 'name' => $s->label()])
            ->toArray();
    }

    public function delete(PurchaseInvoice $purchaseInvoice): void
    {
        // Prevent deletion of invoices already processed by SDI
        if (! $purchaseInvoice->isSdiEditable()) {
            $this->error(__('app.purchase_invoices.cannot_delete_sdi'), position: 'toast-bottom');

            return;
        }

        $purchaseInvoice->delete();
        $this->warning(
            __('app.purchase_invoices.deleted', ['number' => $purchaseInvoice->number]),
            __('app.common.goodbye'),
            position: 'toast-bottom'
        );
    }

    public function headers(): array
    {
        return [
            ['key' => 'number', 'label' => __('app.purchase_invoices.col_number'), 'class' => 'w-40'],
            ['key' => 'date', 'label' => __('app.purchase_invoices.col_date'), 'class' => 'w-32'],
            ['key' => 'contact.name', 'label' => __('app.purchase_invoices.col_supplier'), 'sortable' => false],
            ['key' => 'total_gross', 'label' => __('app.purchase_invoices.col_total'), 'class' => 'w-36 text-right'],
            ['key' => 'status', 'label' => __('app.purchase_invoices.col_status'), 'class' => 'w-32'],
            ['key' => 'payment_status', 'label' => __('app.invoices.col_payment'), 'sortable' => false, 'class' => 'w-36'],
        ];
    }

    public function render()
    {
        $invoices = PurchaseInvoice::query()
            ->with('contact')
            ->whereYear('date', $this->fiscalYear)
            ->when($this->search, fn ($q) => $q->where(
                fn ($q) => $q->where('number', 'like', "%{$this->search}%")
                    ->orWhereHas('contact', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterPayment, fn ($q) => $q->where('payment_status', $this->filterPayment))
            ->orderBy(...array_values($this->sortBy))
            ->paginate(10);

        return view('livewire.purchase-invoices.index', [
            'invoices' => $invoices,
            'headers' => $this->headers(),
            'fiscalYear' => $this->fiscalYear,
            'isReadOnly' => $this->isReadOnly,
        ]);
    }
}
