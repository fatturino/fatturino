<?php

namespace App\Livewire\Invoices;

use App\Contracts\SdiProvider;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\SdiStatus;
use App\Models\Invoice;
use App\Models\SdiLog;
use App\Services\CourtesyPdfService;
use App\Services\DocumentMailer;
use App\Services\InvoiceXmlService;
use App\Services\LocalXmlValidator;
use App\Support\InvoiceAuditDispatcher;
use App\Traits\Toast;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

class Index extends Component
{
    use Toast;
    use WithPagination;

    public string $search = '';

    public bool $drawer = false;

    public array $sortBy = ['column' => 'number', 'direction' => 'desc'];

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

    // Clear filters
    public function clear(): void
    {
        $this->reset(['search', 'drawer', 'sortBy', 'filterStatus', 'filterPayment']);
        $this->resetPage();
        $this->success(__('app.invoices.filters_cleared'), position: 'toast-bottom');
    }

    // Summary stats for the current fiscal year
    public function getStatsProperty(): array
    {
        $base = Invoice::query()->whereYear('date', $this->fiscalYear);

        $totalCount = (clone $base)->count();
        $totalGross = (clone $base)->sum('total_gross');
        $unpaidCount = (clone $base)->where('payment_status', PaymentStatus::Unpaid)->count();
        $unpaidInvoices = (clone $base)->where('payment_status', PaymentStatus::Unpaid)->get();
        $unpaidAmount = $unpaidInvoices->sum(fn ($i) => $i->net_due - $i->total_paid);
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

    // Delete (only if not sent to SDI)
    public function delete(Invoice $invoice): void
    {
        if (! $invoice->isSdiEditable()) {
            $this->error(__('app.invoices.readonly_error'));

            return;
        }

        $invoice->delete();
        $this->warning(
            __('app.invoices.deleted', ['number' => $invoice->number]),
            __('app.common.goodbye'),
            position: 'toast-bottom'
        );
    }

    public function downloadXml(Invoice $invoice, InvoiceXmlService $xmlService)
    {
        try {
            $xml = $xmlService->generate($invoice);
            $filename = 'fattura-'.$invoice->number.'.xml';

            return response()->streamDownload(
                fn () => print ($xml),
                $filename,
                ['Content-Type' => 'application/xml']
            );
        } catch (\Exception $e) {
            $this->error(__('app.invoices.generation_error', ['error' => $e->getMessage()]));
        }
    }

    public function downloadPdf(Invoice $invoice, CourtesyPdfService $pdfService)
    {
        try {
            $pdf = $pdfService->generate($invoice);
            $filename = $pdfService->generateFileName($invoice);

            return response()->streamDownload(
                fn () => print ($pdf->output()),
                $filename,
                ['Content-Type' => 'application/pdf']
            );
        } catch (\Exception $e) {
            $this->error(__('app.invoices.pdf_generation_error', ['error' => $e->getMessage()]));
        }
    }

    public function validateXml(Invoice $invoice, InvoiceXmlService $xmlService, SdiProvider $sdiService, LocalXmlValidator $localValidator): void
    {
        try {
            $xml = $xmlService->generate($invoice);

            // Local structural validation (always runs)
            $localResult = $localValidator->validate($xml);
            if (! $localResult['valid']) {
                $this->error(__('app.invoices.xml_invalid', ['errors' => implode(', ', $localResult['errors'])]));

                return;
            }

            // Remote validation via SDI provider (only if configured)
            if ($sdiService->isConfigured()) {
                $remoteResult = $sdiService->validateXml($xml);
                if (! $remoteResult['valid']) {
                    $this->error(__('app.invoices.xml_invalid', ['errors' => implode(', ', $remoteResult['errors'])]));

                    return;
                }
            }

            $invoice->update(['status' => InvoiceStatus::XmlValidated]);
            $this->success(__('app.invoices.xml_validated_success'));
        } catch (\Exception $e) {
            $this->error(__('app.invoices.generation_error', ['error' => $e->getMessage()]));
        }
    }

    public function sendToSdi(Invoice $invoice, InvoiceXmlService $xmlService, SdiProvider $sdiService): void
    {
        if (! $invoice->status->canSendToSdi()) {
            $this->error(__('app.invoices.cannot_send_not_validated'));

            return;
        }

        if (! $sdiService->isConfigured()) {
            $this->error(__('app.invoices.openapi_not_configured'));

            return;
        }

        try {
            $xml = $xmlService->generate($invoice);
            $fileName = $xmlService->generateFileName($invoice);

            $result = $sdiService->sendInvoice($xml, $fileName);

            if ($result['success']) {
                $invoice->update([
                    'status' => InvoiceStatus::Sent,
                    'sdi_status' => SdiStatus::Sent,
                    'sdi_uuid' => $result['uuid'] ?? null,
                    'sdi_message' => $result['message'] ?? 'Inviata',
                    'sdi_sent_at' => now(),
                ]);

                SdiLog::create([
                    'invoice_id' => $invoice->id,
                    'event_type' => 'sent',
                    'status' => SdiStatus::Sent->value,
                    'message' => $result['message'] ?? 'Fattura inviata a SDI',
                ]);

                InvoiceAuditDispatcher::dispatch($invoice, 'sdi_sent');

                $this->success(__('app.invoices.sent_success'));
            } else {
                $invoice->update([
                    'sdi_status' => SdiStatus::Error,
                    'sdi_message' => $result['error_message'] ?? 'Errore invio',
                ]);

                SdiLog::create([
                    'invoice_id' => $invoice->id,
                    'event_type' => 'error',
                    'status' => SdiStatus::Error->value,
                    'message' => $result['error_message'] ?? 'Errore invio',
                ]);

                $this->error(__('app.invoices.send_error', ['error' => $result['error_message'] ?? __('app.common.unknown')]));
            }
        } catch (\Exception $e) {
            $this->error(__('app.invoices.generation_error', ['error' => $e->getMessage()]));
        }
    }

    /**
     * Quick-send email using the default template, without opening a preview modal.
     */
    public function sendEmail(Invoice $invoice, DocumentMailer $mailer): void
    {
        $recipientEmail = $invoice->contact?->email;

        if (! $recipientEmail) {
            $this->error(__('app.email.no_recipient'));

            return;
        }

        try {
            $mailer->send($invoice, $recipientEmail);
            $this->success(__('app.email.sent_success'));
        } catch (Throwable $e) {
            $this->error(__('app.email.send_error', ['error' => $e->getMessage()]));
        }
    }

    // Headers
    public function headers(): array
    {
        return [
            ['key' => 'number', 'label' => __('app.invoices.col_number'), 'class' => 'w-40', 'render' => fn ($row) => '<span class="font-semibold whitespace-nowrap">'.e($row->number).'</span>'],
            ['key' => 'date', 'label' => __('app.invoices.col_date'), 'class' => 'w-32', 'render' => fn ($row) => '<span class="text-sm">'.$row->date->format('d/m/Y').'</span>'],
            ['key' => 'contact.name', 'label' => __('app.invoices.col_customer'), 'sortable' => false, 'render' => fn ($row) => '<span class="font-medium">'.e($row->contact?->name).'</span>'],
            ['key' => 'total_gross', 'label' => __('app.invoices.col_total'), 'class' => 'w-36 text-right', 'render' => fn ($row) => '<div class="text-right font-semibold">€ '.number_format($row->total_gross / 100, 2, ',', '.').'</div>'],
            ['key' => 'net_due', 'label' => __('app.invoices.col_net_due'), 'class' => 'w-36 text-right', 'render' => fn ($row) => '<div class="text-right font-semibold">€ '.number_format($row->net_due / 100, 2, ',', '.').'</div>'],
            ['key' => 'status', 'label' => __('app.invoices.col_status'), 'class' => 'w-32', 'view' => 'partials.invoice-status-cell'],
            ['key' => 'payment_status', 'label' => __('app.invoices.col_payment'), 'sortable' => false, 'class' => 'w-36', 'view' => 'partials.payment-status-cell'],
            ['key' => 'actions', 'label' => '', 'class' => 'w-1', 'view' => 'partials.invoice-actions'],
        ];
    }

    public function duplicate(Invoice $invoice): void
    {
        if (! $invoice->isSdiEditable()) {
            $this->error(__('app.invoices.readonly_error'));

            return;
        }

        // Clone the invoice as a new draft
        $clone = $invoice->replicate();
        $clone->number = null;
        $clone->sequential_number = null;
        $clone->date = now()->format('Y-m-d');
        $clone->status = InvoiceStatus::Draft;
        $clone->sdi_status = null;
        $clone->sdi_uuid = null;
        $clone->sdi_message = null;
        $clone->sdi_sent_at = null;
        $clone->payment_status = PaymentStatus::Unpaid;
        $clone->paid_amount = 0;
        $clone->save();

        // Clone invoice lines
        foreach ($invoice->lines as $line) {
            $clone->lines()->create($line->replicate()->toArray());
        }

        $clone->calculateTotals();

        $this->success(__('app.invoices.duplicated'));
        $this->redirect('/sell-invoices/'.$clone->id.'/edit', navigate: true);
    }

    public function render()
    {
        $invoices = Invoice::query()
            ->with('contact')
            ->whereYear('date', $this->fiscalYear)
            ->when($this->search, fn ($q) => $q->where(
                fn ($q) => $q->where('number', 'like', "%{$this->search}%")
                    ->orWhereHas('contact', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterPayment, fn ($q) => $q->where('payment_status', $this->filterPayment))
            ->orderBy(
                $this->sortBy['column'] === 'number' ? 'sequential_number' : $this->sortBy['column'],
                $this->sortBy['direction']
            )
            ->paginate(10);

        return view('livewire.invoices.index', [
            'invoices' => $invoices,
            'headers' => $this->headers(),
            'fiscalYear' => $this->fiscalYear,
            'isReadOnly' => $this->isReadOnly,
        ]);
    }
}
