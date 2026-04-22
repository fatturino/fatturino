<?php

namespace App\Livewire\CreditNotes;

use App\Contracts\SdiProvider;
use App\Enums\InvoiceStatus;
use App\Enums\SdiStatus;
use App\Enums\VatRate;
use App\Livewire\Traits\HasPaymentTracking;
use App\Models\Contact;
use App\Models\CreditNote;
use App\Models\Sequence;
use App\Services\CreditNoteXmlService;
use App\Services\CourtesyPdfService;
use App\Services\DocumentStorageService;
use App\Services\DocumentMailer;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Mary\Traits\Toast;

class Edit extends Component
{
    use HasPaymentTracking;
    use Toast;

    public CreditNote $creditNote;

    // True when the credit note cannot be modified (past year or SDI locked)
    public bool $isReadOnly = false;

    // True when the credit note is locked by SDI submission
    public bool $isSdiLocked = false;

    #[Validate('required')]
    public ?string $number = null;

    #[Validate('required|date')]
    public string $date = '';

    #[Validate('required|exists:contacts,id')]
    public ?int $contact_id = null;

    #[Validate('required|exists:sequences,id')]
    public ?int $sequence_id = null;

    public ?string $notes = null;

    // Optional reference to original invoice (DatiFattureCollegate)
    public ?string $related_invoice_number = null;

    public ?string $related_invoice_date = null;

    public array $lines = [];

    public function mount(CreditNote $creditNote): void
    {
        $this->creditNote = $creditNote;
        $this->isSdiLocked = ! $creditNote->isSdiEditable();
        $this->isReadOnly  = $creditNote->date->year < now()->year || $this->isSdiLocked;

        $this->fill($creditNote->only([
            'number', 'date', 'contact_id', 'sequence_id', 'notes',
            'related_invoice_number',
        ]));
        $this->date = $creditNote->date->format('Y-m-d');
        $this->related_invoice_date = $creditNote->related_invoice_date?->format('Y-m-d');

        // Load lines (convert from cents to euros for form display)
        foreach ($creditNote->lines as $line) {
            $this->lines[] = [
                'id'              => $line->id,
                'description'     => $line->description,
                'quantity'        => $line->quantity,
                'unit_of_measure' => $line->unit_of_measure ?? '',
                'unit_price'      => $line->unit_price / 100,
                'vat_rate'        => $line->vat_rate?->value,
                'total'           => $line->total / 100,
            ];
        }
    }

    public function addLine(): void
    {
        $this->lines[] = [
            'description'     => '',
            'quantity'        => 1,
            'unit_of_measure' => '',
            'unit_price'      => 0,
            'vat_rate'        => VatRate::R22->value,
            'total'           => 0,
        ];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function getTotalNetProperty(): float
    {
        $total = 0;
        foreach ($this->lines as $line) {
            $total += (float) $line['quantity'] * (float) $line['unit_price'];
        }

        return $total;
    }

    public function getTotalVatProperty(): float
    {
        $total = 0;
        foreach ($this->lines as $line) {
            $vatRate = VatRate::tryFrom($line['vat_rate'] ?? '');
            if ($vatRate) {
                $lineTotal = (float) $line['quantity'] * (float) $line['unit_price'];
                $total += $lineTotal * ($vatRate->percent() / 100);
            }
        }

        return $total;
    }

    public function getTotalGrossProperty(): float
    {
        return $this->totalNet + $this->totalVat;
    }

    protected function getPayableInvoice(): Model
    {
        return $this->creditNote;
    }

    public function save(): void
    {
        if ($this->isReadOnly) {
            $this->error(__('app.credit_notes.readonly_error'));

            return;
        }

        $this->validate();

        $this->creditNote->update([
            'number'                 => $this->number,
            'date'                   => $this->date,
            'contact_id'             => $this->contact_id,
            'sequence_id'            => $this->sequence_id,
            'notes'                  => $this->notes,
            'related_invoice_number' => $this->related_invoice_number ?: null,
            'related_invoice_date'   => $this->related_invoice_date ?: null,
        ]);

        // Recreate lines (simple sync: delete old, create new)
        $this->creditNote->lines()->delete();

        foreach ($this->lines as $line) {
            $lineTotal = (float) $line['quantity'] * (float) $line['unit_price'];

            $this->creditNote->lines()->create([
                'description'     => $line['description'],
                'quantity'        => $line['quantity'],
                'unit_of_measure' => $line['unit_of_measure'] ?: null,
                'unit_price'      => (int) round($line['unit_price'] * 100),
                'vat_rate'        => $line['vat_rate'],
                'total'           => (int) round($lineTotal * 100),
            ]);
        }

        $this->creditNote->calculateTotals();

        $this->success(__('app.credit_notes.updated'), redirectTo: route('credit-notes.index'));
    }

    public function downloadXml(CreditNoteXmlService $xmlService): mixed
    {
        try {
            $xml = $xmlService->generate($this->creditNote);
            $filename = $xmlService->generateFileName($this->creditNote);

            return response()->streamDownload(
                fn () => print($xml),
                $filename,
                ['Content-Type' => 'application/xml']
            );
        } catch (\Exception $exception) {
            $this->error(__('app.credit_notes.generation_error', ['error' => $exception->getMessage()]));
        }
    }

    public function sendToSdi(CreditNoteXmlService $xmlService, SdiProvider $sdiService, DocumentStorageService $documentStorage, CourtesyPdfService $pdfService): void
    {
        if (! $sdiService->isConfigured()) {
            $this->error(__('app.invoices.openapi_not_configured'));

            return;
        }

        try {
            $xml = $xmlService->generate($this->creditNote);

            $validation = $sdiService->validateXml($xml);
            if (! $validation['valid']) {
                $this->error(__('app.invoices.xml_invalid', ['errors' => implode(', ', $validation['errors'])]));

                return;
            }

            // Persist the validated XML before sending
            $xmlPath = $documentStorage->storeXml(
                $xml,
                'credit-notes',
                $this->creditNote->date->year,
                $xmlService->generateFileName($this->creditNote),
            );

            $fileName = $xmlService->generateFileName($this->creditNote);
            $result = $sdiService->sendInvoice($xml, $fileName);

            if ($result['success']) {
                // Persist the courtesy PDF matching the sent XML
                $pdf = $pdfService->generateForCreditNote($this->creditNote);
                $pdfPath = $documentStorage->storePdf(
                    $pdf->output(),
                    'credit-notes',
                    $this->creditNote->date->year,
                    'nota-di-credito-' . $this->creditNote->number . '.pdf',
                );

                $this->creditNote->update([
                    'sdi_status'  => SdiStatus::Sent,
                    'sdi_uuid'    => $result['uuid'] ?? null,
                    'sdi_message' => $result['message'] ?? 'Inviata',
                    'sdi_sent_at' => now(),
                    'status'      => InvoiceStatus::Sent,
                    'xml_path'    => $xmlPath,
                    'pdf_path'    => $pdfPath,
                ]);

                $this->success(__('app.invoices.sent_success'));
            } else {
                $this->creditNote->update([
                    'sdi_status'  => 'error',
                    'sdi_message' => $result['error_message'] ?? 'Errore invio',
                ]);

                $this->error(__('app.invoices.send_error', ['error' => $result['error_message'] ?? __('app.common.unknown')]));
            }
        } catch (\Exception $exception) {
            $this->error(__('app.credit_notes.generation_error', ['error' => $exception->getMessage()]));
        }
    }

    public function downloadPdf(CourtesyPdfService $pdfService): mixed
    {
        try {
            $pdf = $pdfService->generateForCreditNote($this->creditNote);
            $filename = 'nota-di-credito-' . $this->creditNote->number . '.pdf';

            return response()->streamDownload(
                fn () => print($pdf->output()),
                $filename,
                ['Content-Type' => 'application/pdf']
            );
        } catch (\Exception $exception) {
            $this->error(__('app.credit_notes.generation_error', ['error' => $exception->getMessage()]));
        }
    }

    public function render()
    {
        return view('livewire.credit-notes.edit', [
            'contacts'      => Contact::orderBy('name')->get(),
            'sequences'     => Sequence::where('type', 'credit_note')->orderBy('name')->get(),
            'vatRates'      => VatRate::options(),
            'isReadOnly'    => $this->isReadOnly,
            'sdiConfigured' => app(SdiProvider::class)->isConfigured(),
        ]);
    }
}
