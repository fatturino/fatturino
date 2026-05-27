<?php

namespace App\Services\Domain;

use App\Enums\InvoiceStatus;
use App\Models\FiscalDocument;
use App\Models\Sequence;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FiscalDocumentMutationService
{
    public function __construct(
        private readonly DocumentNumberingService $numbering,
        private readonly FiscalDocumentTotalsService $totals
    ) {}

    /**
     * @param  array<string, mixed>  $header
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function create(array $header, array $lines): FiscalDocument
    {
        return DB::transaction(function () use ($header, $lines) {
            $date = Carbon::parse($header['date']);
            $sequence = Sequence::query()->findOrFail($header['sequence_id']);
            $reserved = $this->numbering->reserve($sequence, $date);

            $document = FiscalDocument::query()->create([
                ...$header,
                'public_id' => (string) str()->ulid(),
                'fiscal_year' => $reserved['fiscal_year'],
                'sequential_number' => $reserved['sequential_number'],
                'number' => $reserved['number'],
                'status' => $header['status'] ?? InvoiceStatus::Draft,
                'payment_status' => $header['payment_status'] ?? 'unpaid',
            ]);

            foreach ($lines as $line) {
                $document->lines()->create($line);
            }

            $this->totals->recalculate($document);

            return $document->fresh(['lines', 'payments']);
        });
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function update(FiscalDocument $document, array $header, array $lines): FiscalDocument
    {
        return DB::transaction(function () use ($document, $header, $lines) {
            if (array_key_exists('date', $header)) {
                $header['fiscal_year'] = Carbon::parse($header['date'])->year;
            }

            $document->update($header);

            $document->lines()->delete();
            foreach ($lines as $line) {
                $document->lines()->create($line);
            }

            $this->totals->recalculate($document);

            return $document->fresh(['lines', 'payments']);
        });
    }
}
