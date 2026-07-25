<?php

use App\Actions\SaveSalesInvoice;
use App\Enums\SdiStatus;
use App\Models\Contact;
use App\Models\SalesInvoice;
use App\Models\Sequence;
use App\Settings\InvoiceSettings;
use Illuminate\Validation\ValidationException;

function salesInvoicePayload(Contact $contact): array
{
    return [
        'contact_id' => $contact->id,
        'date' => now()->toDateString(),
        'document_type' => 'TD01',
        'vat_payability' => 'I',
        'lines' => [[
            'description' => 'Consulenza',
            'quantity' => 1,
            'unit_of_measure' => '',
            'unit_price' => 100,
            'discount_percent' => null,
            'vat_rate' => 'R22',
        ]],
    ];
}

it('requires a configured sales sequence before creating an invoice', function () {
    $settings = app(InvoiceSettings::class);
    $settings->default_sequence_sales = null;
    $settings->save();

    expect(fn () => app(SaveSalesInvoice::class)->create(salesInvoicePayload(Contact::factory()->create())))
        ->toThrow(ValidationException::class);
});

it('rejects updates to sales invoices locked by SDI', function () {
    $sequence = Sequence::factory()->create(['type' => 'sales']);
    $invoice = SalesInvoice::factory()->create([
        'sequence_id' => $sequence->id,
        'date' => now()->toDateString(),
        'sdi_status' => SdiStatus::Delivered,
    ]);
    $invoice = SalesInvoice::query()->findOrFail($invoice->id);

    expect(fn () => app(SaveSalesInvoice::class)->update($invoice, salesInvoicePayload(Contact::factory()->create())))
        ->toThrow(ValidationException::class);
});
