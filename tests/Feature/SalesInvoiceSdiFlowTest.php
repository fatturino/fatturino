<?php

use App\Contracts\SdiProvider;
use App\Enums\InvoiceStatus;
use App\Enums\SdiStatus;
use App\Enums\SdiSubmissionStatus;
use App\Enums\VatRate;
use App\Models\Contact;
use App\Models\EiOutboundLog;
use App\Models\FiscalDocument;
use App\Models\Sequence;
use App\Models\User;
use App\Services\DocumentEventRecorder;
use App\Settings\CompanySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $settings = app(CompanySettings::class);
    $settings->company_name = 'Test Company SRL';
    $settings->company_vat_number = '12345678903';
    $settings->company_tax_code = '12345678903';
    $settings->company_address = 'Via Test 1';
    $settings->company_city = 'Milano';
    $settings->company_postal_code = '20100';
    $settings->company_province = 'MI';
    $settings->company_country = 'IT';
    $settings->company_fiscal_regime = 'RF01';
    $settings->save();
});

test('validate xml endpoint sets invoice status to xml_validated', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create(['country' => 'IT', 'sdi_code' => '1234567']);

    $invoice = FiscalDocument::factory()->create([
        'contact_id' => $contact->id,
        'status' => InvoiceStatus::Draft,
    ]);

    $invoice->lines()->create([
        'description' => 'Servizio',
        'quantity' => 1,
        'unit_price' => 10000,
        'vat_rate' => VatRate::R22->value,
        'total' => 10000,
    ]);
    $invoice->calculateTotals();

    $provider = Mockery::mock(SdiProvider::class);
    $provider->shouldReceive('validateXml')
        ->once()
        ->andReturn(['valid' => true, 'message' => 'ok']);
    app()->instance(SdiProvider::class, $provider);

    $response = $this->actingAs($user)->postJson("/sell-invoices/{$invoice->id}/validate-xml");

    $response->assertOk()->assertJson([
        'success' => true,
        'document' => [
            'status' => InvoiceStatus::XmlValidated->value,
            'sdi_status' => null,
            'is_sdi_editable' => true,
        ],
    ]);
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::XmlValidated);
    $this->assertDatabaseHas('document_events', [
        'fiscal_document_id' => $invoice->id,
        'event_type' => 'xml_validated',
        'channel' => 'xml',
        'status' => 'success',
    ]);
});

test('send to sdi endpoint sends only xml validated invoices', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create(['country' => 'IT', 'sdi_code' => '1234567']);

    $invoice = FiscalDocument::factory()->create([
        'contact_id' => $contact->id,
        'status' => InvoiceStatus::Draft,
    ]);

    $invoice->lines()->create([
        'description' => 'Servizio',
        'quantity' => 1,
        'unit_price' => 10000,
        'vat_rate' => VatRate::R22->value,
        'total' => 10000,
    ]);
    $invoice->calculateTotals();

    $provider = Mockery::mock(SdiProvider::class);
    $provider->shouldReceive('sendInvoice')->never();
    app()->instance(SdiProvider::class, $provider);

    $response = $this->actingAs($user)->postJson("/sell-invoices/{$invoice->id}/send-sdi");

    $response->assertStatus(422)->assertJson(['success' => false]);
});

test('send to sdi endpoint returns updated document payload on success', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create(['country' => 'IT', 'sdi_code' => '1234567']);

    $invoice = FiscalDocument::factory()->create([
        'contact_id' => $contact->id,
        'status' => InvoiceStatus::XmlValidated,
    ]);

    $invoice->lines()->create([
        'description' => 'Servizio',
        'quantity' => 1,
        'unit_price' => 10000,
        'vat_rate' => VatRate::R22->value,
        'total' => 10000,
    ]);
    $invoice->calculateTotals();

    $provider = Mockery::mock(SdiProvider::class);
    $provider->shouldReceive('id')->once()->andReturn('mock-provider');
    $provider->shouldReceive('sendInvoice')
        ->once()
        ->andReturn([
            'success' => true,
            'uuid' => 'uuid-123',
            'file_id' => 'file-456',
            'message' => 'Fattura inviata con successo allo SDI',
        ]);
    app()->instance(SdiProvider::class, $provider);

    $response = $this->actingAs($user)->postJson("/sell-invoices/{$invoice->id}/send-sdi");

    $response->assertOk()->assertJson([
        'success' => true,
        'document' => [
            'status' => InvoiceStatus::Sent->value,
            'sdi_status' => SdiStatus::Sent->value,
            'is_sdi_editable' => false,
        ],
    ]);

    $invoice->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Sent->value)
        ->and($invoice->sdi_status)->toBe(SdiStatus::Sent->value)
        ->and($invoice->sdi_uuid)->toBe('uuid-123');

    $this->assertDatabaseHas('ei_outbound_logs', [
        'fiscal_document_id' => $invoice->id,
        'event_type' => 'sent',
        'status' => SdiStatus::Sent->value,
    ]);
    $this->assertDatabaseHas('document_events', [
        'fiscal_document_id' => $invoice->id,
        'event_type' => 'sdi_sent',
        'channel' => 'sdi',
        'status' => 'success',
    ]);
});

test('send to sdi endpoint stays successful when document event logging fails after provider success', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create(['country' => 'IT', 'sdi_code' => '1234567']);

    $invoice = FiscalDocument::factory()->create([
        'contact_id' => $contact->id,
        'status' => InvoiceStatus::XmlValidated,
    ]);

    $invoice->lines()->create([
        'description' => 'Servizio',
        'quantity' => 1,
        'unit_price' => 10000,
        'vat_rate' => VatRate::R22->value,
        'total' => 10000,
    ]);
    $invoice->calculateTotals();

    $provider = Mockery::mock(SdiProvider::class);
    $provider->shouldReceive('id')->once()->andReturn('mock-provider');
    $provider->shouldReceive('sendInvoice')
        ->once()
        ->andReturn([
            'success' => true,
            'uuid' => 'uuid-logging-fails',
            'file_id' => 'file-logging-fails',
            'message' => 'Fattura inviata con successo allo SDI',
        ]);
    app()->instance(SdiProvider::class, $provider);

    $recorder = Mockery::mock(DocumentEventRecorder::class);
    $recorder->shouldReceive('sdiSent')
        ->once()
        ->andThrow(new RuntimeException('event log unavailable'));
    app()->instance(DocumentEventRecorder::class, $recorder);

    $response = $this->actingAs($user)->postJson("/sell-invoices/{$invoice->id}/send-sdi");

    $response->assertOk()->assertJson([
        'success' => true,
        'document' => [
            'status' => InvoiceStatus::Sent->value,
            'sdi_status' => SdiStatus::Sent->value,
            'is_sdi_editable' => false,
        ],
    ]);

    $invoice->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Sent->value)
        ->and($invoice->sdi_status)->toBe(SdiStatus::Sent->value)
        ->and($invoice->sdi_uuid)->toBe('uuid-logging-fails');
});

test('validate xml endpoint returns uniform json when document is not editable', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create(['country' => 'IT', 'sdi_code' => '1234567']);

    $invoice = FiscalDocument::factory()->create([
        'contact_id' => $contact->id,
        'status' => InvoiceStatus::Draft,
        'sdi_status' => SdiStatus::Delivered,
    ]);

    $response = $this->actingAs($user)->postJson("/sell-invoices/{$invoice->id}/validate-xml");

    $response->assertStatus(422)->assertJson([
        'success' => false,
        'document' => [
            'status' => InvoiceStatus::Draft->value,
            'sdi_status' => SdiStatus::Delivered->value,
            'is_sdi_editable' => false,
        ],
    ]);
});

test('send to sdi endpoint logs send_failed and returns uniform json on provider failure', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create(['country' => 'IT', 'sdi_code' => '1234567']);

    $invoice = FiscalDocument::factory()->create([
        'contact_id' => $contact->id,
        'status' => InvoiceStatus::XmlValidated,
    ]);

    $invoice->lines()->create([
        'description' => 'Servizio',
        'quantity' => 1,
        'unit_price' => 10000,
        'vat_rate' => VatRate::R22->value,
        'total' => 10000,
    ]);
    $invoice->calculateTotals();

    $provider = Mockery::mock(SdiProvider::class);
    $provider->shouldReceive('sendInvoice')
        ->once()
        ->andReturn([
            'success' => false,
            'error_message' => 'Provider offline',
        ]);
    app()->instance(SdiProvider::class, $provider);

    $response = $this->actingAs($user)->postJson("/sell-invoices/{$invoice->id}/send-sdi");

    $response->assertStatus(422)->assertJson([
        'success' => false,
        'error' => 'Provider offline',
        'document' => [
            'status' => InvoiceStatus::XmlValidated->value,
            'sdi_status' => null,
            'is_sdi_editable' => true,
        ],
    ]);

    $log = EiOutboundLog::query()
        ->where('fiscal_document_id', $invoice->id)
        ->where('event_type', 'send_failed')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->status)->toBe(SdiStatus::Error)
        ->and($log->message)->toBe('Provider offline');
});

test('a retry after an ambiguous provider outcome does not invoke the provider twice', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create(['country' => 'IT', 'sdi_code' => '1234567']);
    $invoice = FiscalDocument::factory()->create([
        'contact_id' => $contact->id,
        'status' => InvoiceStatus::XmlValidated,
    ]);
    $invoice->lines()->create([
        'description' => 'Servizio',
        'quantity' => 1,
        'unit_price' => 10000,
        'vat_rate' => VatRate::R22->value,
        'total' => 10000,
    ]);
    $invoice->calculateTotals();

    $provider = Mockery::mock(SdiProvider::class);
    $provider->shouldReceive('sendInvoice')->once()->andReturn([
        'success' => false,
        'outcome_unknown' => true,
        'error_message' => 'Timeout della connessione',
    ]);
    app()->instance(SdiProvider::class, $provider);

    $this->actingAs($user)->postJson("/sell-invoices/{$invoice->id}/send-sdi")
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    $this->actingAs($user)->postJson("/sell-invoices/{$invoice->id}/send-sdi")
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    $this->assertDatabaseHas('sdi_outbound_submissions', [
        'fiscal_document_id' => $invoice->id,
        'status' => SdiSubmissionStatus::OutcomeUnknown->value,
        'active_document_lock' => (string) $invoice->id,
    ]);
});

test('updating a rejected invoice resets workflow status to draft so it can be revalidated', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create(['country' => 'IT', 'sdi_code' => '1234567']);
    $sequence = Sequence::factory()->forType('sales')->create();

    $invoice = FiscalDocument::factory()->create([
        'contact_id' => $contact->id,
        'sequence_id' => $sequence->id,
        'status' => InvoiceStatus::Sent,
        'sdi_status' => SdiStatus::Rejected,
        'document_type' => 'TD01',
    ]);

    $invoice->lines()->create([
        'description' => 'Servizio',
        'quantity' => 1,
        'unit_price' => 10000,
        'vat_rate' => VatRate::R22->value,
        'total' => 10000,
    ]);

    $response = $this->actingAs($user)->put("/sell-invoices/{$invoice->id}", [
        'contact_id' => $contact->id,
        'sequence_id' => $sequence->id,
        'date' => now()->format('Y-m-d'),
        'due_date' => now()->addDays(30)->format('Y-m-d'),
        'document_type' => 'TD01',
        'notes' => 'Correzione dopo scarto SDI',
        'withholding_tax_enabled' => false,
        'fund_enabled' => false,
        'stamp_duty_applied' => false,
        'payment_method' => null,
        'payment_terms' => null,
        'bank_name' => null,
        'bank_iban' => null,
        'vat_payability' => 'I',
        'split_payment' => false,
        'lines' => [[
            'description' => 'Servizio corretto',
            'quantity' => 1,
            'unit_of_measure' => 'pz',
            'unit_price' => 11000,
            'discount_percent' => 0,
            'vat_rate' => VatRate::R22->value,
        ]],
    ]);

    $response->assertRedirect(route('sell-invoices.index'));

    $invoice->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Draft->value)
        ->and($invoice->sdi_status)->toBe(SdiStatus::Rejected->value)
        ->and($invoice->isSdiEditable())->toBeTrue();
});

afterEach(function () {
    Mockery::close();
});
