<?php

use App\Enums\PaymentMethod;
use App\Enums\ProformaStatus;
use App\Models\Contact;
use App\Models\ProformaInvoice;
use App\Models\Sequence;
use App\Models\User;
use App\Settings\CompanySettings;
use App\Settings\InvoiceSettings;
use Livewire\Livewire;

function validProformaLine(string $description = 'Progetto'): array
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

it('renders and creates a proforma through the Livewire form', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create();
    $sequence = Sequence::factory()->create(['type' => 'proforma', 'pattern' => 'PR-{SEQ}-{ANNO}']);

    $this->actingAs($user)
        ->get(route('proforma.create'))
        ->assertOk()
        ->assertSeeLivewire('pages::documents.proforma.form');

    Livewire::test('pages::documents.proforma.form')
        ->set('contact_id', $contact->id)
        ->set('lines', [validProformaLine()])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('proforma.index'));

    $this->assertDatabaseHas('fiscal_documents', [
        'type' => 'proforma',
        'sequence_id' => $sequence->id,
        'total_net' => 10000,
        'total_vat' => 2200,
        'total_gross' => 12200,
    ]);
});

it('renders the proforma as a document editor with progressive disclosure', function () {
    $user = User::factory()->create();
    Sequence::factory()->create(['type' => 'proforma']);

    $this->actingAs($user);

    Livewire::test('pages::documents.proforma.form')
        ->assertSee('Dati proforma')
        ->assertSee('Cliente, numero e condizioni del documento.')
        ->assertSee('Descrizione')
        ->assertSee('Quantità')
        ->assertSee('Prezzo')
        ->assertSee('Pagamento')
        ->assertSee('Note')
        ->assertSee('Opzioni fiscali')
        ->assertDontSee('aria-label="Sezioni proforma"', escape: false);
});

it('updates an editable proforma without changing its sequence', function () {
    $user = User::factory()->create();
    $sequence = Sequence::factory()->create(['type' => 'proforma']);
    $invoice = ProformaInvoice::factory()->create(['sequence_id' => $sequence->id, 'date' => now()->toDateString()]);
    $invoice->lines()->create(['description' => 'Prima versione', 'quantity' => 1, 'unit_price' => 10000, 'vat_rate' => 'R22', 'total' => 10000]);
    $invoice = ProformaInvoice::query()->findOrFail($invoice->id);

    $this->actingAs($user);

    Livewire::test('pages::documents.proforma.form', ['proformaInvoice' => $invoice])
        ->set('lines', [validProformaLine('Versione aggiornata')])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('proforma.index'));

    $invoice->refresh();
    expect($invoice->sequence_id)->toBe($sequence->id)
        ->and($invoice->lines()->sole()->description)->toBe('Versione aggiornata');
});

it('loads a payment method enum into the editable proforma form', function () {
    $invoice = ProformaInvoice::factory()->create([
        'payment_method' => PaymentMethod::MP05,
    ]);

    Livewire::test('pages::documents.proforma.form', ['proformaInvoice' => $invoice])
        ->assertSet('payment_method', PaymentMethod::MP05->value);
});

it('creates a proforma with the configured default sequence', function () {
    $contact = Contact::factory()->create();
    Sequence::factory()->create(['type' => 'proforma', 'is_system' => true]);
    $defaultSequence = Sequence::factory()->create(['type' => 'proforma', 'is_system' => false]);
    $settings = app(InvoiceSettings::class);
    $settings->default_sequence_proforma = $defaultSequence->id;
    $settings->save();

    Livewire::test('pages::documents.proforma.form')
        ->set('contact_id', $contact->id)
        ->set('lines', [validProformaLine()])
        ->call('save')
        ->assertHasNoErrors();

    expect(ProformaInvoice::query()->sole()->sequence_id)->toBe($defaultSequence->id);
});

it('normalizes RF19 proformas and protects their total from withholding defaults', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create();
    Sequence::factory()->create(['type' => 'proforma']);
    $settings = app(CompanySettings::class);
    $settings->company_fiscal_regime = 'RF19';
    $settings->save();

    $this->actingAs($user);

    Livewire::test('pages::documents.proforma.form')
        ->assertDontSee("Ritenuta d'acconto")
        ->set('contact_id', $contact->id)
        ->set('lines', [[...validProformaLine(), 'unit_price' => '10.00']])
        ->assertSet('withholding_tax_enabled', false)
        ->assertSee('€ 10,00')
        ->call('save')
        ->assertHasNoErrors();

    $invoice = ProformaInvoice::query()->sole();
    expect($invoice->withholding_tax_enabled)->toBeFalse()
        ->and($invoice->net_due)->toBe(1000)
        ->and($invoice->lines()->sole()->vat_rate->value)->toBe('N2.2');
});

it('applies RF19 stamp duty to proformas with the same customer charge behavior as sales invoices', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create();
    Sequence::factory()->create(['type' => 'proforma']);
    $settings = app(CompanySettings::class);
    $settings->company_fiscal_regime = 'RF19';
    $settings->save();

    $this->actingAs($user);

    Livewire::test('pages::documents.proforma.form')
        ->set('contact_id', $contact->id)
        ->set('lines', [[...validProformaLine(), 'unit_price' => '100.00']])
        ->set('stamp_duty_charged_to_customer', false)
        ->assertSet('stamp_duty_applied', true)
        ->assertSee('Bollo a carico cedente')
        ->assertSee('€ 100,00')
        ->call('save')
        ->assertHasNoErrors();

    $invoice = ProformaInvoice::query()->sole();
    expect($invoice->stamp_duty_applied)->toBeTrue()
        ->and($invoice->stamp_duty_charged_to_customer)->toBeFalse()
        ->and($invoice->stamp_duty_amount)->toBe(200)
        ->and($invoice->net_due)->toBe(10000);
});

it('includes fund contribution and its VAT in the proforma preview', function () {
    Sequence::factory()->create(['type' => 'proforma']);

    Livewire::test('pages::documents.proforma.form')
        ->set('lines', [validProformaLine()])
        ->set('fund_enabled', true)
        ->set('fund_percent', '4.00')
        ->set('fund_vat_rate', 'R22')
        ->assertSee('Cassa previdenziale')
        ->assertSee('€ 4,00')
        ->assertSee('€ 126,88');
});

it('renders converted and historical proformas as read-only', function () {
    $user = User::factory()->create();
    $sequence = Sequence::factory()->create(['type' => 'proforma']);
    $invoice = ProformaInvoice::factory()->create([
        'sequence_id' => $sequence->id,
        'status' => ProformaStatus::Converted,
        'date' => now()->subYear()->toDateString(),
    ]);
    $invoice = ProformaInvoice::query()->findOrFail($invoice->id);

    $this->actingAs($user);

    Livewire::test('pages::documents.proforma.form', ['proformaInvoice' => $invoice])
        ->assertSee('Questa proforma non è più modificabile.')
        ->assertDontSee('Aggiorna proforma');
});
