<?php

use App\Enums\InvoiceStatus;
use App\Models\Contact;
use App\Models\SalesInvoice;
use App\Models\Sequence;
use App\Models\User;
use App\Settings\CompanySettings;
use App\Settings\InvoiceSettings;
use Livewire\Livewire;

function configureSalesInvoiceFormSequence(): Sequence
{
    $sequence = Sequence::factory()->create(['type' => 'sales', 'pattern' => 'FV-{SEQ}-{ANNO}']);
    $settings = app(InvoiceSettings::class);
    $settings->default_sequence_sales = $sequence->id;
    $settings->save();

    return $sequence;
}

function validSalesInvoiceLine(string $description = 'Consulenza'): array
{
    return [
        'key' => (string) str()->uuid(),
        'description' => $description,
        'quantity' => '1',
        'unit_of_measure' => '',
        'unit_price' => '100.00',
        'discount_percent' => '',
        'vat_rate' => 'R22',
        'details_enabled' => false,
        'discount_enabled' => false,
    ];
}

it('renders the sales invoice create page as a Livewire form', function () {
    $user = User::factory()->create();
    configureSalesInvoiceFormSequence();

    $this->actingAs($user)
        ->get(route('sell-invoices.create'))
        ->assertOk()
        ->assertSeeLivewire('pages::documents.sales.form')
        ->assertSee('Righe fattura');
});

it('creates a sales invoice through the Livewire form using the configured sales sequence', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create();
    $sequence = configureSalesInvoiceFormSequence();

    $this->actingAs($user);

    Livewire::test('pages::documents.sales.form')
        ->set('contact_id', $contact->id)
        ->set('date', now()->toDateString())
        ->set('document_type', 'TD01')
        ->set('lines', [validSalesInvoiceLine()])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('sell-invoices.index'));

    $this->assertDatabaseHas('fiscal_documents', [
        'contact_id' => $contact->id,
        'sequence_id' => $sequence->id,
        'type' => 'sales',
        'number' => 'FV-1-'.now()->year,
        'total_net' => 10000,
        'total_vat' => 2200,
        'total_gross' => 12200,
    ]);
    $this->assertDatabaseHas('fiscal_documents_lines', ['description' => 'Consulenza', 'total' => 10000]);
});

it('updates an editable sales invoice through the Livewire form without changing its sequence', function () {
    $user = User::factory()->create();
    $sequence = configureSalesInvoiceFormSequence();
    $invoice = SalesInvoice::factory()->create([
        'sequence_id' => $sequence->id,
        'date' => now()->toDateString(),
        'document_type' => 'TD01',
        'status' => InvoiceStatus::XmlValidated,
    ]);
    $invoice->lines()->create([
        'description' => 'Versione iniziale',
        'quantity' => 1,
        'unit_price' => 10000,
        'vat_rate' => 'R22',
        'total' => 10000,
    ]);
    $invoice = SalesInvoice::query()->findOrFail($invoice->id);

    $this->actingAs($user);

    Livewire::test('pages::documents.sales.form', ['invoice' => $invoice])
        ->set('lines', [validSalesInvoiceLine('Versione aggiornata')])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('sell-invoices.index'));

    $invoice->refresh();
    expect($invoice->sequence_id)->toBe($sequence->id)
        ->and($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->lines()->sole()->description)->toBe('Versione aggiornata');
});

it('renders fiscal options compatible with RF19 and normalizes the saved document', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create();
    configureSalesInvoiceFormSequence();
    $settings = app(CompanySettings::class);
    $settings->company_fiscal_regime = 'RF19';
    $settings->save();

    $this->actingAs($user);

    Livewire::test('pages::documents.sales.form')
        ->assertDontSee("Ritenuta d'acconto")
        ->assertDontSee('Split payment')
        ->assertDontSee('Esigibilità IVA')
        ->set('contact_id', $contact->id)
        ->set('date', now()->toDateString())
        ->set('lines', [validSalesInvoiceLine()])
        ->call('save')
        ->assertHasNoErrors();

    $invoice = SalesInvoice::query()->sole();
    expect($invoice->withholding_tax_enabled)->toBeFalse()
        ->and($invoice->split_payment)->toBeFalse()
        ->and($invoice->vat_payability)->toBe('I')
        ->and($invoice->lines()->sole()->vat_rate->value)->toBe('N2.2');
});

it('does not subtract withholding tax from RF19 invoice totals', function (string $amount) {
    $user = User::factory()->create();
    $settings = app(CompanySettings::class);
    $settings->company_fiscal_regime = 'RF19';
    $settings->save();
    $invoiceSettings = app(InvoiceSettings::class);
    $invoiceSettings->withholding_tax_enabled = true;
    $invoiceSettings->save();
    $contact = Contact::factory()->create();
    configureSalesInvoiceFormSequence();

    $this->actingAs($user);

    Livewire::test('pages::documents.sales.form')
        ->set('lines', [[...validSalesInvoiceLine(), 'unit_price' => $amount]])
        ->assertSet('withholding_tax_enabled', false)
        ->assertSee('€ '.number_format((float) $amount, 2, ',', '.'))
        ->set('contact_id', $contact->id)
        ->call('save')
        ->assertHasNoErrors();

    $expectedNetDue = (float) $amount > 77.47 ? (float) $amount + 2 : (float) $amount;

    expect(SalesInvoice::query()->sole()->net_due)->toBe((int) round($expectedNetDue * 100));
})->with(['10 euro' => '10.00', '200 euro' => '200.00']);

it('renders historical and SDI-locked sales invoices as read-only', function () {
    $user = User::factory()->create();
    $sequence = configureSalesInvoiceFormSequence();
    $historical = SalesInvoice::factory()->create([
        'sequence_id' => $sequence->id,
        'date' => now()->subYear()->toDateString(),
    ]);
    $historical = SalesInvoice::query()->findOrFail($historical->id);

    $this->actingAs($user);

    Livewire::test('pages::documents.sales.form', ['invoice' => $historical])
        ->assertSee('Questa fattura non è più modificabile.')
        ->assertDontSee('Aggiorna fattura');
});
