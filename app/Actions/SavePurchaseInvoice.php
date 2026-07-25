<?php

namespace App\Actions;

use App\Models\FiscalDocument;
use App\Models\PurchaseInvoice;
use App\Services\Domain\FiscalDocumentMutationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SavePurchaseInvoice
{
    public function __construct(private readonly FiscalDocumentMutationService $mutationService) {}

    /** @param array<string, mixed> $payload */
    public function update(PurchaseInvoice $purchaseInvoice, array $payload): FiscalDocument
    {
        return DB::transaction(function () use ($purchaseInvoice, $payload) {
            $record = FiscalDocument::query()->lockForUpdate()->findOrFail($purchaseInvoice->id);
            /** @var PurchaseInvoice $locked */
            $locked = (new PurchaseInvoice)->newFromBuilder($record->getAttributes());
            if (! $locked->isSdiEditable() || $locked->date?->year < now()->year) {
                throw ValidationException::withMessages(['invoice' => 'Questa fattura non è più modificabile.']);
            }

            return $this->mutationService->update($locked, [
                'number' => $payload['number'],
                'date' => $payload['date'],
                'due_date' => $payload['due_date'] ?? null,
                'contact_id' => $payload['contact_id'],
                'sequence_id' => $locked->sequence_id,
            ], array_map($this->buildLinePayload(...), $payload['lines']));
        });
    }

    /** @param array<string, mixed> $line */
    private function buildLinePayload(array $line): array
    {
        $total = (float) $line['quantity'] * (float) $line['unit_price'];

        return ['description' => $line['description'], 'quantity' => (float) $line['quantity'], 'unit_of_measure' => ($line['unit_of_measure'] ?? null) ?: null, 'unit_price' => (int) round((float) $line['unit_price'] * 100), 'discount_percent' => null, 'discount_amount' => null, 'vat_rate' => $line['vat_rate'], 'total' => (int) round($total * 100)];
    }
}
