<?php

namespace App\Livewire\Proforma;

use App\Actions\ConvertProformaToInvoice;
use App\Enums\PaymentStatus;
use App\Enums\ProformaStatus;
use App\Models\ProformaInvoice;
use App\Services\CourtesyPdfService;
use App\Services\DocumentMailer;
use Livewire\Component;
use Livewire\WithPagination;
use App\Traits\Toast;
use Throwable;

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
        $this->success(__('app.proforma.filters_cleared'), position: 'toast-bottom');
    }

    public function getStatsProperty(): array
    {
        $base = ProformaInvoice::query()->whereYear('date', $this->fiscalYear);

        $totalCount = (clone $base)->count();
        $totalGross = (clone $base)->sum('total_gross');
        $unpaidCount = (clone $base)
            ->where('payment_status', PaymentStatus::Unpaid)
            ->whereNotIn('status', [ProformaStatus::Cancelled->value])
            ->count();
        $convertedCount = (clone $base)->where('status', ProformaStatus::Converted)->count();

        return [
            'total_count' => $totalCount,
            'total_gross' => $totalGross,
            'unpaid_count' => $unpaidCount,
            'converted_count' => $convertedCount,
        ];
    }

    public function getStatusOptionsProperty(): array
    {
        return collect(ProformaStatus::cases())
            ->map(fn ($s) => ['id' => $s->value, 'name' => $s->label()])
            ->toArray();
    }

    public function getPaymentOptionsProperty(): array
    {
        return collect(PaymentStatus::cases())
            ->map(fn ($s) => ['id' => $s->value, 'name' => $s->label()])
            ->toArray();
    }

    /**
     * Delete a proforma (only if not converted).
     */
    public function delete(ProformaInvoice $proformaInvoice): void
    {
        if ($proformaInvoice->status === ProformaStatus::Converted) {
            $this->error(__('app.proforma.already_converted'));

            return;
        }

        $proformaInvoice->delete();
        $this->warning(
            __('app.proforma.deleted', ['number' => $proformaInvoice->number]),
            __('app.common.goodbye'),
            position: 'toast-bottom'
        );
    }

    /**
     * Convert proforma to a sales invoice.
     */
    public function convertToInvoice(ProformaInvoice $proformaInvoice): void
    {
        $action = app(ConvertProformaToInvoice::class);
        $invoice = $action->execute($proformaInvoice);

        if (! $invoice) {
            $this->error(__('app.proforma.cannot_convert'));

            return;
        }

        $this->success(
            __('app.proforma.converted_success', ['number' => $invoice->number]),
            redirectTo: route('sell-invoices.edit', $invoice)
        );
    }

    /**
     * Quick-send email to the proforma contact using the default template.
     */
    public function downloadPdf(ProformaInvoice $proformaInvoice, CourtesyPdfService $pdfService)
    {
        try {
            $pdf = $pdfService->generateForProforma($proformaInvoice);
            $filename = $pdfService->generateProformaFileName($proformaInvoice);

            return response()->streamDownload(
                fn () => print ($pdf->output()),
                $filename,
                ['Content-Type' => 'application/pdf']
            );
        } catch (\Exception $e) {
            $this->error(__('app.invoices.pdf_generation_error', ['error' => $e->getMessage()]));
        }
    }

    public function sendEmail(ProformaInvoice $proformaInvoice, DocumentMailer $mailer): void
    {
        $recipientEmail = $proformaInvoice->contact?->email;

        if (! $recipientEmail) {
            $this->error(__('app.email.no_recipient'));

            return;
        }

        try {
            $mailer->send($proformaInvoice, $recipientEmail);
            $this->success(__('app.email.sent_success'));
        } catch (Throwable $e) {
            $this->error(__('app.email.send_error', ['error' => $e->getMessage()]));
        }
    }

    public function headers(): array
    {
        return [
            ['key' => 'number', 'label' => __('app.proforma.col_number'), 'class' => 'w-40', 'render' => fn($row) => '<span class="font-semibold whitespace-nowrap">' . e($row->number) . '</span>'],
            ['key' => 'date', 'label' => __('app.proforma.col_date'), 'class' => 'w-32', 'render' => fn($row) => '<span class="text-sm">' . $row->date->format('d/m/Y') . '</span>'],
            ['key' => 'contact.name', 'label' => __('app.proforma.col_customer'), 'sortable' => false, 'render' => fn($row) => '<span class="font-medium">' . e($row->contact?->name) . '</span>'],
            ['key' => 'total_gross', 'label' => __('app.proforma.col_total'), 'class' => 'w-36 text-right', 'render' => fn($row) => '<div class="text-right font-semibold">€ ' . number_format($row->total_gross / 100, 2, ',', '.') . '</div>'],
            ['key' => 'status', 'label' => __('app.proforma.col_status'), 'class' => 'w-32', 'view' => 'partials.proforma-status-cell'],
            ['key' => 'payment_status', 'label' => __('app.proforma.col_payment'), 'sortable' => false, 'class' => 'w-36', 'view' => 'partials.payment-status-cell'],
            ['key' => 'actions', 'label' => '', 'class' => 'w-1', 'view' => 'partials.proforma-actions'],
        ];
    }

    public function render()
    {
        $proformas = ProformaInvoice::query()
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

        return view('livewire.proforma.index', [
            'proformas' => $proformas,
            'headers' => $this->headers(),
            'fiscalYear' => $this->fiscalYear,
            'isReadOnly' => $this->isReadOnly,
        ]);
    }
}
