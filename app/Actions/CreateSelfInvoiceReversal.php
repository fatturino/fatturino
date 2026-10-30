<?php

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\SdiStatus;
use App\Models\EiInboundLog;
use App\Models\EiOutboundLog;
use App\Models\FiscalDocument;
use App\Models\SelfInvoice;
use App\Services\DocumentSequenceResolver;
use App\Services\Domain\DocumentNumberingService;
use App\Services\Domain\FiscalDocumentMutationService;
use App\Settings\CompanySettings;
use App\Support\FiscalRegimePolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateSelfInvoiceReversal
{
    private const REVERSIBLE_DOCUMENT_TYPES = ['TD17', 'TD18', 'TD19'];

    public function __construct(
        private readonly FiscalDocumentMutationService $mutationService,
        private readonly DocumentNumberingService $numbering,
        private readonly DocumentSequenceResolver $sequenceResolver,
        private readonly CompanySettings $companySettings,
    ) {}

    public function execute(SelfInvoice $invoice): SelfInvoice
    {
        $this->ensureAllowed();

        return DB::transaction(function () use ($invoice): SelfInvoice {
            $record = FiscalDocument::query()->lockForUpdate()->findOrFail($invoice->id);
            /** @var SelfInvoice $source */
            $source = (new SelfInvoice)->newFromBuilder($record->getAttributes());
            $source->load('lines');

            $this->recoverSdiId($source);
            $this->ensureReversible($source);

            if (SelfInvoice::query()->where('reversal_of_id', $source->id)->exists()) {
                throw ValidationException::withMessages([
                    'invoice' => 'Questa autofattura è già stata stornata.',
                ]);
            }

            $sequence = $this->sequenceResolver->resolve('self_invoice');
            $date = now()->toDateString();
            $numbering = $this->numbering->reserve($sequence, $date);

            $reversal = $this->mutationService->create([
                'date' => $date,
                'due_date' => null,
                'contact_id' => $source->contact_id,
                'sequence_id' => $sequence->id,
                'document_type' => $source->document_type,
                'related_invoice_number' => $source->number,
                'related_invoice_date' => $source->date,
                'reversal_of_id' => $source->id,
                'notes' => sprintf(
                    'Storno integrale dell’autofattura %s del %s.',
                    $source->number,
                    $source->date->format('d/m/Y'),
                ),
                'type' => 'self_invoice',
                'status' => InvoiceStatus::Draft,
                'payment_status' => PaymentStatus::Paid,
            ], $source->lines->map(fn ($line): array => [
                'description' => $line->description,
                'quantity' => abs((float) $line->quantity),
                'unit_of_measure' => $line->unit_of_measure,
                'unit_price' => -abs((int) $line->unit_price),
                'discount_percent' => null,
                'discount_amount' => null,
                'vat_rate' => $line->vat_rate,
                'total' => -abs((int) $line->total),
            ])->all(), $numbering);

            return SelfInvoice::query()->with(['lines', 'reversalOf'])->findOrFail($reversal->id);
        });
    }

    private function ensureAllowed(): void
    {
        if (! FiscalRegimePolicy::supportsSelfInvoices($this->companySettings->company_fiscal_regime, $this->companySettings->rf19_self_invoices_enabled)) {
            throw ValidationException::withMessages(['invoice' => 'Le autofatture sono disabilitate per il regime fiscale corrente.']);
        }
    }

    private function ensureReversible(SelfInvoice $invoice): void
    {
        if (! in_array($invoice->document_type, self::REVERSIBLE_DOCUMENT_TYPES, true)) {
            throw ValidationException::withMessages(['invoice' => 'Lo storno è disponibile solo per autofatture TD17, TD18 e TD19.']);
        }

        if ($invoice->status !== InvoiceStatus::Sent || in_array($invoice->sdi_status, [SdiStatus::Rejected, SdiStatus::Error], true)) {
            throw ValidationException::withMessages(['invoice' => 'Puoi stornare solo un’autofattura già inviata allo SDI e non scartata.']);
        }

        if ($invoice->reversal_of_id !== null) {
            throw ValidationException::withMessages(['invoice' => 'Non puoi creare uno storno di un altro storno.']);
        }

        if (blank($invoice->sdi_id)) {
            throw ValidationException::withMessages([
                'invoice' => 'Non è possibile stornare l’autofattura perché manca l’IdSdI della comunicazione originale.',
            ]);
        }
    }

    private function recoverSdiId(SelfInvoice $invoice): void
    {
        if (filled($invoice->sdi_id)) {
            return;
        }

        $payloads = EiOutboundLog::query()
            ->where('fiscal_document_id', $invoice->id)
            ->latest('id')
            ->pluck('raw_payload')
            ->merge(EiInboundLog::query()
                ->where('linked_fiscal_document_id', $invoice->id)
                ->latest('id')
                ->pluck('raw_payload'));

        foreach ($payloads as $payload) {
            $sdiId = data_get($payload, 'notification.message.identificativo_sdi')
                ?? data_get($payload, 'data.notification.message.identificativo_sdi');

            if (filled($sdiId)) {
                $invoice->newQueryWithoutScopes()
                    ->whereKey($invoice)
                    ->update(['sdi_id' => (string) $sdiId]);
                $invoice->refresh();

                return;
            }
        }
    }
}
