<?php

use App\Actions\CreateSelfInvoiceReversal;
use App\Actions\SaveSelfInvoice;
use App\Enums\InvoiceStatus;
use App\Enums\SdiStatus;
use App\Enums\VatRate;
use App\Models\Contact;
use App\Models\EiOutboundLog;
use App\Models\SelfInvoice;
use App\Models\Sequence;
use App\Models\User;
use App\Services\LocalXmlValidator;
use App\Services\SelfInvoiceXmlService;
use App\Settings\InvoiceSettings;
use Illuminate\Validation\ValidationException;

function sentSelfInvoiceForReversal(string $documentType = 'TD17'): SelfInvoice
{
    $sequence = Sequence::factory()->create(['type' => 'self_invoice']);
    $settings = app(InvoiceSettings::class);
    $settings->default_sequence_self_invoice = $sequence->id;
    $settings->save();

    $invoice = SelfInvoice::factory()->create([
        'number' => 'AUTO-42',
        'date' => '2026-10-28',
        'contact_id' => Contact::factory()->create()->id,
        'document_type' => $documentType,
        'related_invoice_number' => 'SUP-2026-77',
        'related_invoice_date' => '2026-10-20',
        'status' => InvoiceStatus::Sent,
        'sdi_status' => SdiStatus::Delivered,
        'sdi_id' => '18519436588',
    ]);
    $invoice->lines()->create([
        'description' => 'Servizio estero',
        'quantity' => 2,
        'unit_of_measure' => 'ore',
        'unit_price' => 5000,
        'total' => 10000,
        'vat_rate' => VatRate::R22,
    ]);
    $invoice->calculateTotals();

    return $invoice->fresh('lines');
}

it('creates an immutable negative draft linked to the sent self invoice', function () {
    $source = sentSelfInvoiceForReversal();

    $reversal = app(CreateSelfInvoiceReversal::class)->execute($source);

    expect($reversal)
        ->toBeInstanceOf(SelfInvoice::class)
        ->and($reversal->status)->toBe(InvoiceStatus::Draft)
        ->and($reversal->document_type)->toBe('TD17')
        ->and($reversal->reversal_of_id)->toBe($source->id)
        ->and($reversal->related_invoice_number)->toBe($source->number)
        ->and($reversal->related_invoice_date->toDateString())->toBe('2026-10-28')
        ->and($reversal->total_net)->toBe(-10000)
        ->and($reversal->total_vat)->toBe(-2200)
        ->and($reversal->total_gross)->toBe(-12200)
        ->and($reversal->lines->sole()->quantity)->toBe('2.00000000')
        ->and($reversal->lines->sole()->unit_price)->toBe(-5000)
        ->and($reversal->lines->sole()->total)->toBe(-10000);
});

it('creates a reversal through the authenticated endpoint and returns its editor URL', function () {
    $user = User::factory()->create();
    $source = sentSelfInvoiceForReversal('TD19');

    $response = $this->actingAs($user)->postJson(route('self-invoices.reverse', $source));

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Bozza di storno creata. Controlla il documento prima di validarlo e inviarlo allo SDI.');

    $reversal = SelfInvoice::query()->where('reversal_of_id', $source->id)->sole();

    $response->assertJsonPath('redirect_url', route('self-invoices.edit', $reversal));
    $this->assertDatabaseHas('fiscal_documents', [
        'id' => $reversal->id,
        'reversal_of_id' => $source->id,
        'status' => InvoiceStatus::Draft->value,
    ]);
});

it('creates an XML document with the source self invoice as its related document', function () {
    $reversal = app(CreateSelfInvoiceReversal::class)->execute(sentSelfInvoiceForReversal('TD18'));

    $xml = app(SelfInvoiceXmlService::class)->generate($reversal->fresh(['lines', 'contact']));

    expect($xml)
        ->toContain('<TipoDocumento>TD18</TipoDocumento>')
        ->toContain('<IdDocumento>AUTO-42</IdDocumento>')
        ->toContain('<IdDocumento>18519436588</IdDocumento>')
        ->toContain('<PrezzoTotale>-100.00000000</PrezzoTotale>')
        ->toContain('<ImponibileImporto>-100.00</ImponibileImporto>');
    expect(substr_count($xml, '<DatiFattureCollegate>'))->toBe(2);
    expect(app(LocalXmlValidator::class)->validate($xml)['valid'])->toBeTrue();
});

it('recovers and persists the official SDI identifier from historical logs before creating a reversal', function () {
    $source = sentSelfInvoiceForReversal();
    $source->update(['sdi_id' => null]);
    EiOutboundLog::create([
        'fiscal_document_id' => $source->id,
        'event_type' => 'RC',
        'status' => SdiStatus::Delivered,
        'raw_payload' => [
            'notification' => [
                'message' => ['identificativo_sdi' => '18519436588'],
            ],
        ],
    ]);

    $reversal = app(CreateSelfInvoiceReversal::class)->execute($source);

    expect($source->fresh()->sdi_id)->toBe('18519436588');
    expect(app(SelfInvoiceXmlService::class)->generate($reversal->fresh(['lines', 'contact'])))
        ->toContain('<IdDocumento>18519436588</IdDocumento>');
});

it('refuses a reversal when the official SDI identifier cannot be recovered', function () {
    $source = sentSelfInvoiceForReversal();
    $source->update(['sdi_id' => null]);

    expect(fn () => app(CreateSelfInvoiceReversal::class)->execute($source->fresh()))
        ->toThrow(ValidationException::class);
});

it('does not create a second reversal for the same self invoice', function () {
    $source = sentSelfInvoiceForReversal();
    app(CreateSelfInvoiceReversal::class)->execute($source);

    expect(fn () => app(CreateSelfInvoiceReversal::class)->execute($source))
        ->toThrow(ValidationException::class);
});

it('only allows sent TD17, TD18, and TD19 self invoices to be reversed', function (string $type, InvoiceStatus $status, ?SdiStatus $sdiStatus) {
    $source = sentSelfInvoiceForReversal($type);
    $source->update(['status' => $status, 'sdi_status' => $sdiStatus]);

    expect(fn () => app(CreateSelfInvoiceReversal::class)->execute($source->fresh()))
        ->toThrow(ValidationException::class);
})->with([
    'unsupported TD28' => ['TD28', InvoiceStatus::Sent, SdiStatus::Delivered],
    'draft document' => ['TD17', InvoiceStatus::Draft, null],
    'rejected document' => ['TD17', InvoiceStatus::Sent, SdiStatus::Rejected],
]);

it('does not allow a reversal draft to be modified', function () {
    $reversal = app(CreateSelfInvoiceReversal::class)->execute(sentSelfInvoiceForReversal());
    $payload = [
        'contact_id' => $reversal->contact_id,
        'date' => $reversal->date->toDateString(),
        'due_date' => null,
        'document_type' => $reversal->document_type,
        'related_invoice_number' => $reversal->related_invoice_number,
        'related_invoice_date' => $reversal->related_invoice_date->toDateString(),
        'notes' => $reversal->notes,
        'lines' => [[
            'description' => 'Altered line',
            'quantity' => 1,
            'unit_of_measure' => null,
            'unit_price' => 1,
            'vat_rate' => VatRate::R22->value,
        ]],
    ];

    expect(fn () => app(SaveSelfInvoice::class)->update($reversal, $payload))
        ->toThrow(ValidationException::class);
});
