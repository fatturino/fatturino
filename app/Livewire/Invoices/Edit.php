<?php

namespace App\Livewire\Invoices;

use App\Contracts\SdiProvider;
use App\Enums\InvoiceStatus;
use App\Enums\SdiStatus;
use App\Enums\VatRate;
use App\Livewire\Traits\CalculatesInvoiceTotals;
use App\Livewire\Traits\HandlesReverseCalculation;
use App\Livewire\Traits\HasEmailSending;
use App\Livewire\Traits\HasPaymentTracking;
use App\Livewire\Traits\ManagesInvoiceLines;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\SdiLog;
use App\Models\Sequence;
use App\Services\CourtesyPdfService;
use App\Services\DocumentStorageService;
use App\Services\InvoiceXmlService;
use App\Services\LocalXmlValidator;
use App\Settings\InvoiceSettings;
use App\Support\InvoiceAuditDispatcher;
use App\Traits\Toast;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Edit extends Component
{
    use CalculatesInvoiceTotals,
        HandlesReverseCalculation,
        HasEmailSending,
        HasPaymentTracking,
        ManagesInvoiceLines,
        Toast;

    public Invoice $invoice;

    // Active tab in the edit view ('details' or 'history')
    public string $activeTab = 'details';

    // True when the invoice cannot be modified (past year or SDI locked)
    public bool $isReadOnly = false;

    // True when the invoice is locked by SDI submission
    public bool $isSdiLocked = false;

    #[Validate('required')]
    public ?string $number = null;

    #[Validate('required|date')]
    public string $date = '';

    #[Validate('required|exists:contacts,id')]
    public ?int $contact_id = null;

    #[Validate('required|exists:sequences,id')]
    public ?int $sequence_id = null;

    // Withholding tax (Ritenuta d'acconto)
    public bool $withholding_tax_enabled = false;

    public string $withholding_tax_percent = '20.00';

    // Professional fund (Cassa Previdenziale)
    public bool $fund_enabled = false;

    public ?string $fund_type = null;

    public string $fund_percent = '4.00';

    public ?string $fund_vat_rate = null;

    public bool $fund_has_deduction = false;

    // Stamp duty (Marca da bollo)
    public bool $stamp_duty_applied = false;

    public bool $auto_stamp_duty = false;

    public string $stamp_duty_threshold = '77.47';

    // Payment details (DatiPagamento)
    public ?string $payment_method = null;

    public ?string $payment_terms = null;

    public ?string $due_date = null;

    public ?string $bank_name = null;

    public ?string $bank_iban = null;

    // VAT payability and split payment
    public string $vat_payability = 'I';

    public bool $split_payment = false;

    // Notes / Causale (used as document description in XML)
    public ?string $notes = null;

    // Document type (TD01, TD02, TD03, TD06, TD24, TD25)
    public string $document_type = 'TD01';

    public function mount(Invoice $invoice)
    {
        $this->invoice = $invoice;
        $this->isSdiLocked = ! $invoice->isSdiEditable();
        $this->isReadOnly = $invoice->date->year < now()->year || $this->isSdiLocked;

        $this->fill($invoice->only(['number', 'date', 'contact_id', 'sequence_id']));
        $this->date = $invoice->date->format('Y-m-d');

        // Load withholding tax settings from the invoice record
        $this->withholding_tax_enabled = $invoice->withholding_tax_enabled ?? false;
        $this->withholding_tax_percent = $invoice->withholding_tax_percent ?? '20.00';

        // Load fund from invoice record
        $this->fund_enabled = $invoice->fund_enabled ?? false;
        $this->fund_type = $invoice->fund_type;
        $this->fund_percent = $invoice->fund_percent ?? '4.00';
        $this->fund_vat_rate = $invoice->fund_vat_rate?->value;
        $this->fund_has_deduction = $invoice->fund_has_deduction ?? false;

        // Load stamp duty from invoice record, with settings for auto-toggle
        $settings = app(InvoiceSettings::class);
        $this->stamp_duty_applied = $invoice->stamp_duty_applied ?? false;
        $this->auto_stamp_duty = $settings->auto_stamp_duty;
        $this->stamp_duty_threshold = $settings->stamp_duty_threshold;

        // Load payment details from invoice
        $this->payment_method = $invoice->payment_method?->value;
        $this->payment_terms = $invoice->payment_terms?->value;
        $this->due_date = $invoice->due_date?->format('Y-m-d');
        $this->bank_name = $invoice->bank_name;
        $this->bank_iban = $invoice->bank_iban;

        // Load VAT payability and split payment from invoice
        $this->vat_payability = $invoice->vat_payability ?? 'I';
        $this->split_payment = $invoice->split_payment ?? false;

        // Load notes and document type
        $this->notes = $invoice->notes;
        $this->document_type = $invoice->document_type ?? 'TD01';

        // Load lines
        foreach ($invoice->lines as $line) {
            $this->lines[] = [
                'id' => $line->id,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_of_measure' => $line->unit_of_measure ?? '',
                'unit_price' => $line->unit_price / 100, // Convert from cents
                'discount_percent' => $line->discount_percent,
                'vat_rate' => $line->vat_rate?->value,
                'total' => $line->total / 100, // Convert from cents
            ];
        }
    }

    protected function getPayableInvoice(): Model
    {
        return $this->invoice;
    }

    public function save()
    {
        if ($this->isReadOnly) {
            $this->error(__('app.invoices.readonly_error'));

            return;
        }

        $this->validate();

        // Editing invalidates a previous XML validation
        $newStatus = $this->invoice->status === InvoiceStatus::XmlValidated
            ? InvoiceStatus::Draft
            : $this->invoice->status;

        $this->invoice->update([
            'number' => $this->number,
            'date' => $this->date,
            'contact_id' => $this->contact_id,
            'sequence_id' => $this->sequence_id,
            'status' => $newStatus,
            'document_type' => $this->document_type,
            'withholding_tax_enabled' => $this->withholding_tax_enabled,
            'withholding_tax_percent' => $this->withholding_tax_enabled ? $this->withholding_tax_percent : null,
            'fund_enabled' => $this->fund_enabled,
            'fund_type' => $this->fund_enabled ? $this->fund_type : null,
            'fund_percent' => $this->fund_enabled ? $this->fund_percent : null,
            'fund_vat_rate' => $this->fund_enabled ? $this->fund_vat_rate : null,
            'fund_has_deduction' => $this->fund_enabled && $this->fund_has_deduction,
            'stamp_duty_applied' => $this->stamp_duty_applied,
            'stamp_duty_amount' => $this->stamp_duty_applied ? 200 : 0,
            'payment_method' => $this->payment_method ?: null,
            'payment_terms' => $this->payment_terms ?: null,
            'due_date' => $this->due_date ?: null,
            'bank_name' => $this->bank_name ?: null,
            'bank_iban' => $this->bank_iban ?: null,
            'vat_payability' => $this->split_payment ? 'S' : $this->vat_payability,
            'split_payment' => $this->split_payment,
            'notes' => $this->notes ?: null,
        ]);

        // Sync lines: delete old ones and recreate (simple approach for now)
        $this->invoice->lines()->delete();

        foreach ($this->lines as $line) {
            $this->invoice->lines()->create($this->buildLinePayload($line));
        }

        // Recalculate totals
        $this->invoice->calculateTotals();

        $this->success(__('app.invoices.updated'));
        $this->redirect('/sell-invoices', navigate: true);
    }

    public function downloadXml(InvoiceXmlService $xmlService)
    {
        try {
            $xml = $xmlService->generate($this->invoice);
            $filename = $xmlService->generateFileName($this->invoice);

            return response()->streamDownload(
                fn () => print ($xml),
                $filename,
                ['Content-Type' => 'application/xml']
            );
        } catch (\Exception $e) {
            $this->error(__('app.invoices.generation_error', ['error' => $e->getMessage()]));
        }
    }

    public function downloadPdf(CourtesyPdfService $pdfService)
    {
        try {
            $pdf = $pdfService->generate($this->invoice);
            $filename = $pdfService->generateFileName($this->invoice);

            return response()->streamDownload(
                fn () => print ($pdf->output()),
                $filename,
                ['Content-Type' => 'application/pdf']
            );
        } catch (\Exception $e) {
            $this->error(__('app.invoices.pdf_generation_error', ['error' => $e->getMessage()]));
        }
    }

    public function validateXml(InvoiceXmlService $xmlService, SdiProvider $sdiService, DocumentStorageService $documentStorage, LocalXmlValidator $localValidator)
    {
        if ($this->isReadOnly) {
            $this->error(__('app.invoices.readonly_error'));

            return;
        }

        try {
            $xml = $xmlService->generate($this->invoice);

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

            // Persist the validated XML — this is exactly the version that will be sent to SDI
            $xmlPath = $documentStorage->storeXml(
                $xml,
                'sales',
                $this->invoice->date->year,
                $xmlService->generateFileName($this->invoice),
            );

            $this->invoice->update([
                'status' => InvoiceStatus::XmlValidated,
                'xml_path' => $xmlPath,
            ]);

            $this->success(__('app.invoices.xml_validated_success'));
        } catch (\Exception $e) {
            $this->error(__('app.invoices.generation_error', ['error' => $e->getMessage()]));
        }
    }

    public function sendToSdi(InvoiceXmlService $xmlService, SdiProvider $sdiService, CourtesyPdfService $pdfService, DocumentStorageService $documentStorage)
    {
        if (! $this->invoice->status->canSendToSdi()) {
            $this->error(__('app.invoices.cannot_send_not_validated'));

            return;
        }

        if (! $sdiService->isConfigured()) {
            $this->error(__('app.invoices.openapi_not_configured'));

            return;
        }

        try {
            $xml = $xmlService->generate($this->invoice);
            $fileName = $xmlService->generateFileName($this->invoice);
            $result = $sdiService->sendInvoice($xml, $fileName);

            if ($result['success']) {
                // Persist the courtesy PDF matching the sent XML
                $pdf = $pdfService->generate($this->invoice);
                $pdfPath = $documentStorage->storePdf(
                    $pdf->output(),
                    'sales',
                    $this->invoice->date->year,
                    $pdfService->generateFileName($this->invoice),
                );

                $this->invoice->update([
                    'status' => InvoiceStatus::Sent,
                    'sdi_status' => SdiStatus::Sent,
                    'sdi_uuid' => $result['uuid'] ?? null,
                    'sdi_message' => $result['message'] ?? 'Inviata',
                    'sdi_sent_at' => now(),
                    'pdf_path' => $pdfPath,
                ]);

                // Log the submission event
                SdiLog::create([
                    'invoice_id' => $this->invoice->id,
                    'event_type' => 'sent',
                    'status' => SdiStatus::Sent->value,
                    'message' => $result['message'] ?? 'Fattura inviata a SDI',
                ]);

                InvoiceAuditDispatcher::dispatch($this->invoice, 'sdi_sent');

                $this->triggerAutoSend('auto_send_sales');
                $this->success(__('app.invoices.sent_success'));
            } else {
                $this->invoice->update([
                    'sdi_status' => SdiStatus::Error,
                    'sdi_message' => $result['error_message'] ?? 'Errore invio',
                ]);

                // Log the error
                SdiLog::create([
                    'invoice_id' => $this->invoice->id,
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

    protected function getEmailDocument(): Model
    {
        return $this->invoice;
    }

    protected function getEmailDocumentType(): string
    {
        return 'sales';
    }

    public function render()
    {
        return view('livewire.invoices.edit', [
            'contacts' => Contact::orderBy('name')->get(),
            'sequences' => Sequence::orderBy('name')->get(),
            'vatRates' => VatRate::options(),
            'isReadOnly' => $this->isReadOnly,
            'isSdiLocked' => $this->isSdiLocked,
            'sdiConfigured' => app(SdiProvider::class)->isConfigured(),
            'sdiLogs' => $this->invoice->sdiLogs()->latest()->get(),
            'latestEmailAudit' => $this->invoice->audits()
                ->where('event', 'email_sent')
                ->latest()
                ->first(),
        ]);
    }
}
