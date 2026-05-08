<?php

namespace App\Livewire\SelfInvoices;

use App\Contracts\SdiProvider;
use App\Enums\InvoiceStatus;
use App\Enums\SdiStatus;
use App\Enums\VatRate;
use App\Livewire\Traits\HasPaymentTracking;
use App\Models\Contact;
use App\Models\SelfInvoice;
use App\Models\Sequence;
use App\Services\DocumentStorageService;
use App\Services\SelfInvoiceXmlService;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Validate;
use Livewire\Component;
use App\Traits\Toast;

class Edit extends Component
{
    use HasPaymentTracking, Toast;

    public SelfInvoice $selfInvoice;

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

    // Self-invoice specific fields
    #[Validate('required|in:TD17,TD18,TD19,TD28,TD29')]
    public string $document_type = 'TD17';

    #[Validate('required')]
    public ?string $related_invoice_number = null;

    #[Validate('required|date')]
    public ?string $related_invoice_date = null;

    public array $lines = [];

    public function mount(SelfInvoice $selfInvoice): void
    {
        $this->selfInvoice = $selfInvoice;
        $this->isSdiLocked = ! $selfInvoice->isSdiEditable();
        $this->isReadOnly = $selfInvoice->date->year < now()->year || $this->isSdiLocked;

        $this->fill($selfInvoice->only([
            'number', 'date', 'contact_id', 'sequence_id',
            'document_type', 'related_invoice_number',
        ]));
        $this->date = $selfInvoice->date->format('Y-m-d');
        $this->related_invoice_date = $selfInvoice->related_invoice_date?->format('Y-m-d');

        // Load lines (convert from cents to euros for form display)
        foreach ($selfInvoice->lines as $line) {
            $this->lines[] = [
                'id' => $line->id,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_of_measure' => $line->unit_of_measure ?? '',
                'unit_price' => $line->unit_price / 100,
                'vat_rate' => $line->vat_rate?->value,
                'total' => $line->total / 100,
            ];
        }
    }

    public function addLine(): void
    {
        $this->lines[] = [
            'description' => '',
            'quantity' => 1,
            'unit_of_measure' => '',
            'unit_price' => 0,
            'vat_rate' => VatRate::R22->value,
            'total' => 0,
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
            // Cast to float: wire:model binds values as strings, which causes TypeError in PHP 8
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
        return $this->selfInvoice;
    }

    public function save(): void
    {
        if ($this->isReadOnly) {
            $this->error(__('app.self_invoices.readonly_error'));

            return;
        }

        $this->validate();

        $this->selfInvoice->update([
            'number' => $this->number,
            'date' => $this->date,
            'contact_id' => $this->contact_id,
            'sequence_id' => $this->sequence_id,
            'document_type' => $this->document_type,
            'related_invoice_number' => $this->related_invoice_number,
            'related_invoice_date' => $this->related_invoice_date,
        ]);

        // Recreate lines (simple sync: delete old, create new)
        $this->selfInvoice->lines()->delete();

        foreach ($this->lines as $line) {
            $lineTotal = (float) $line['quantity'] * (float) $line['unit_price'];

            $this->selfInvoice->lines()->create([
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_of_measure' => $line['unit_of_measure'] ?: null,
                'unit_price' => (int) round($line['unit_price'] * 100),
                'vat_rate' => $line['vat_rate'],
                'total' => (int) round($lineTotal * 100),
            ]);
        }

        $this->selfInvoice->calculateTotals();

        $this->success(__('app.self_invoices.updated'), redirectTo: route('self-invoices.index'));
    }

    public function downloadXml(SelfInvoiceXmlService $xmlService): mixed
    {
        try {
            $xml = $xmlService->generate($this->selfInvoice);
            $filename = $xmlService->generateFileName($this->selfInvoice);

            return response()->streamDownload(
                fn () => print ($xml),
                $filename,
                ['Content-Type' => 'application/xml']
            );
        } catch (\Exception $exception) {
            $this->error(__('app.self_invoices.generation_error', ['error' => $exception->getMessage()]));
        }
    }

    public function sendToSdi(SelfInvoiceXmlService $xmlService, SdiProvider $sdiService, DocumentStorageService $documentStorage): void
    {
        if (! $sdiService->isConfigured()) {
            $this->error(__('app.invoices.openapi_not_configured'));

            return;
        }

        try {
            $xml = $xmlService->generate($this->selfInvoice);

            // Validate XML locally before sending
            $validation = $sdiService->validateXml($xml);
            if (! $validation['valid']) {
                $this->error(__('app.invoices.xml_invalid', ['errors' => implode(', ', $validation['errors'])]));

                return;
            }

            // Persist the validated XML before sending
            $xmlPath = $documentStorage->storeXml(
                $xml,
                'self-invoices',
                $this->selfInvoice->date->year,
                $xmlService->generateFileName($this->selfInvoice),
            );

            $fileName = $xmlService->generateFileName($this->selfInvoice);
            $result = $sdiService->sendInvoice($xml, $fileName);

            if ($result['success']) {
                $this->selfInvoice->update([
                    'sdi_status' => SdiStatus::Sent,
                    'sdi_uuid' => $result['uuid'] ?? null,
                    'sdi_message' => $result['message'] ?? 'Inviata',
                    'sdi_sent_at' => now(),
                    'status' => InvoiceStatus::Sent,
                    'xml_path' => $xmlPath,
                ]);

                $this->success(__('app.invoices.sent_success'));
            } else {
                $this->selfInvoice->update([
                    'sdi_status' => 'error',
                    'sdi_message' => $result['error_message'] ?? 'Errore invio',
                ]);

                $this->error(__('app.invoices.send_error', ['error' => $result['error_message'] ?? __('app.common.unknown')]));
            }
        } catch (\Exception $exception) {
            $this->error(__('app.self_invoices.generation_error', ['error' => $exception->getMessage()]));
        }
    }

    public function documentTypeOptions(): array
    {
        return collect(SelfInvoiceXmlService::DOCUMENT_TYPES)
            ->map(fn ($label, $code) => ['id' => $code, 'name' => $label])
            ->values()
            ->all();
    }

    public function render()
    {
        return view('livewire.self-invoices.edit', [
            'contacts' => Contact::orderBy('name')->get(),
            'sequences' => Sequence::where('type', 'self_invoice')->orderBy('name')->get(),
            'vatRates' => VatRate::options(),
            'documentTypeOptions' => $this->documentTypeOptions(),
            'isReadOnly' => $this->isReadOnly,
            'sdiConfigured' => app(SdiProvider::class)->isConfigured(),
        ]);
    }
}
