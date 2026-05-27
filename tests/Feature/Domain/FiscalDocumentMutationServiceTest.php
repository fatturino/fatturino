<?php

use App\Enums\PaymentStatus;
use App\Enums\VatRate;
use App\Models\Contact;
use App\Models\FiscalDocument;
use App\Models\Payment;
use App\Models\Sequence;
use App\Services\Domain\FiscalDocumentMutationService;
use App\Services\Domain\FiscalDocumentTotalsService;

function baseHeader(int $contactId, int $sequenceId, string $date = '2026-03-10'): array
{
    return [
        'type' => 'sales',
        'contact_id' => $contactId,
        'sequence_id' => $sequenceId,
        'date' => $date,
        'document_type' => 'TD01',
        'status' => 'draft',
        'payment_status' => 'unpaid',
    ];
}

function baseLine(int $unitPriceCents, string $vatRate = 'N4'): array
{
    return [
        'description' => 'Line',
        'quantity' => 1,
        'unit_of_measure' => 'pz',
        'unit_price' => $unitPriceCents,
        'discount_percent' => null,
        'discount_amount' => null,
        'vat_rate' => $vatRate,
        'total' => $unitPriceCents,
    ];
}

test('create is atomic and assigns sequence number with recalculated totals', function () {
    $contact = Contact::factory()->create();
    $sequence = Sequence::factory()->create(['pattern' => 'FE-{SEQ}-{ANNO}']);

    /** @var FiscalDocumentMutationService $service */
    $service = app(FiscalDocumentMutationService::class);

    $document = $service->create(
        baseHeader($contact->id, $sequence->id),
        [baseLine(10000, VatRate::R22->value)]
    );

    expect($document->fiscal_year)->toBe(2026)
        ->and($document->sequential_number)->toBe(1)
        ->and($document->number)->toBe('FE-1-2026')
        ->and($document->total_net)->toBe(10000)
        ->and($document->total_vat)->toBe(2200)
        ->and($document->total_gross)->toBe(12200)
        ->and($document->payment_status)->toBe(PaymentStatus::Unpaid);
});

test('create increments numbering for same sequence and year', function () {
    $contact = Contact::factory()->create();
    $sequence = Sequence::factory()->create(['pattern' => 'INV-{SEQ}']);

    /** @var FiscalDocumentMutationService $service */
    $service = app(FiscalDocumentMutationService::class);

    $first = $service->create(baseHeader($contact->id, $sequence->id), [baseLine(1000)]);
    $second = $service->create(baseHeader($contact->id, $sequence->id), [baseLine(2000)]);

    expect($first->sequential_number)->toBe(1)
        ->and($second->sequential_number)->toBe(2)
        ->and($first->number)->toBe('INV-1')
        ->and($second->number)->toBe('INV-2');
});

test('update replaces all lines and payment status reflects overpayment', function () {
    $contact = Contact::factory()->create();
    $sequence = Sequence::factory()->create(['pattern' => 'INV-{SEQ}']);

    /** @var FiscalDocumentMutationService $service */
    $service = app(FiscalDocumentMutationService::class);

    $document = $service->create(baseHeader($contact->id, $sequence->id), [baseLine(5000)]);

    $updated = $service->update(
        $document,
        ['notes' => 'updated'],
        [baseLine(10000), baseLine(5000)]
    );

    Payment::query()->create([
        'fiscal_document_id' => $updated->id,
        'amount' => 17000,
        'paid_at' => '2026-03-15',
    ]);

    /** @var FiscalDocumentTotalsService $totals */
    $totals = app(FiscalDocumentTotalsService::class);
    $totals->recalculate($updated);

    $reloaded = FiscalDocument::query()->findOrFail($updated->id);

    expect($reloaded->lines()->count())->toBe(2)
        ->and($reloaded->total_net)->toBe(15000)
        ->and($reloaded->total_vat)->toBe(0)
        ->and($reloaded->total_gross)->toBe(15000)
        ->and($reloaded->total_paid)->toBe(17000)
        ->and($reloaded->payment_status)->toBe(PaymentStatus::Paid)
        ->and($reloaded->overpaid_amount)->toBe(2000);
});
