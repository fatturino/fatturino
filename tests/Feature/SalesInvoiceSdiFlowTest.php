<?php

use App\Contracts\SdiProvider;
use App\Enums\InvoiceStatus;
use App\Enums\SdiStatus;
use App\Enums\SdiSubmissionStatus;
use App\Enums\VatRate;
use App\Jobs\SendDocumentToSdiJob;
use App\Models\Contact;
use App\Models\DocumentEvent;
use App\Models\EiOutboundLog;
use App\Models\FiscalDocument;
use App\Models\InAppNotification;
use App\Models\SdiOutboundSubmission;
use App\Models\Sequence;
use App\Models\User;
use App\Services\DocumentEventRecorder;
use App\Settings\CompanySettings;
use App\Settings\OpenApiSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('sdi.driver', 'openapi_legacy');

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
    expect($invoice->fresh()->xml_path)
        ->toContain('documents/xml/sales/'.$invoice->date->year.'/'.$invoice->public_id.'/');
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

test('send to sdi endpoint queues submission and returns queued document payload', function () {
    Queue::fake();

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
    $provider->shouldReceive('sendInvoice')->never();
    app()->instance(SdiProvider::class, $provider);

    $response = $this->actingAs($user)->postJson("/sell-invoices/{$invoice->id}/send-sdi");

    $response->assertOk()->assertJson([
        'success' => true,
        'message' => 'Invio allo SDI accodato.',
        'document' => [
            'status' => InvoiceStatus::XmlValidated->value,
            'sdi_status' => null,
            'is_sdi_editable' => true,
        ],
    ]);

    $this->assertDatabaseHas('sdi_outbound_submissions', [
        'fiscal_document_id' => $invoice->id,
        'provider' => 'openapi_legacy',
        'status' => SdiSubmissionStatus::Dispatching->value,
        'active_document_lock' => (string) $invoice->id,
    ]);
    $this->assertDatabaseHas('document_events', [
        'fiscal_document_id' => $invoice->id,
        'event_type' => 'sdi_queued',
        'channel' => 'sdi',
        'status' => 'queued',
    ]);

    Queue::assertPushed(SendDocumentToSdiJob::class, function (SendDocumentToSdiJob $job) use ($invoice, $user) {
        return $job->actingUserId === $user->id
            && SdiOutboundSubmission::query()->find($job->submissionId)?->fiscal_document_id === $invoice->id;
    });
});

test('queued sdi job finalizes the document after provider acceptance', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $contact = Contact::factory()->create(['country' => 'IT', 'sdi_code' => '1234567']);

    $invoice = FiscalDocument::factory()->create([
        'contact_id' => $contact->id,
        'status' => InvoiceStatus::XmlValidated,
        'number' => 'FV-2026-100',
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
            'success' => true,
            'uuid' => 'uuid-123',
            'file_id' => 'file-456',
            'message' => 'Fattura inviata con successo allo SDI',
        ]);
    app()->instance(SdiProvider::class, $provider);

    $this->actingAs($user)->postJson("/sell-invoices/{$invoice->id}/send-sdi")
        ->assertOk()
        ->assertJsonPath('message', 'Invio allo SDI accodato.');

    $invoice->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Sent->value)
        ->and($invoice->sdi_status)->toBe(SdiStatus::Sent->value)
        ->and($invoice->sdi_uuid)->toBe('uuid-123');

    $this->assertDatabaseHas('ei_outbound_logs', [
        'fiscal_document_id' => $invoice->id,
        'event_type' => 'sent',
        'status' => SdiStatus::Sent->value,
    ]);
    $this->assertDatabaseHas('sdi_outbound_submissions', [
        'fiscal_document_id' => $invoice->id,
        'provider' => 'openapi_legacy',
        'status' => SdiSubmissionStatus::Completed->value,
    ]);
    expect(SdiOutboundSubmission::query()->where('fiscal_document_id', $invoice->id)->sole()->dispatch_started_at)->not->toBeNull();
    $this->assertDatabaseHas('document_events', [
        'fiscal_document_id' => $invoice->id,
        'event_type' => 'sdi_sent',
        'channel' => 'sdi',
        'status' => 'success',
    ]);
    expect(InAppNotification::query()->where('type', 'sdi.send.accepted')->count())->toBe(1);
});

test('queued sdi job stays successful when document event logging fails after provider success', function () {
    $user = User::factory()->create(['is_admin' => true]);
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
            'success' => true,
            'uuid' => 'uuid-logging-fails',
            'file_id' => 'file-logging-fails',
            'message' => 'Fattura inviata con successo allo SDI',
        ]);
    app()->instance(SdiProvider::class, $provider);

    $recorder = Mockery::mock(DocumentEventRecorder::class)->makePartial();
    $recorder->shouldReceive('sdiQueued')->andReturn(null);
    $recorder->shouldReceive('sdiSent')
        ->once()
        ->andThrow(new RuntimeException('event log unavailable'));
    app()->instance(DocumentEventRecorder::class, $recorder);

    $this->actingAs($user)->postJson("/sell-invoices/{$invoice->id}/send-sdi")
        ->assertOk();

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

test('queued sdi job logs send_failed and notifies on provider failure', function () {
    $user = User::factory()->create(['is_admin' => true]);
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

    $this->actingAs($user)->postJson("/sell-invoices/{$invoice->id}/send-sdi")
        ->assertOk()
        ->assertJsonPath('success', true);

    $invoice->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::XmlValidated->value)
        ->and($invoice->sdi_status)->toBeNull();

    $log = EiOutboundLog::query()
        ->where('fiscal_document_id', $invoice->id)
        ->where('event_type', 'send_failed')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->status)->toBe(SdiStatus::Error)
        ->and($log->message)->toBe('Provider offline');

    expect(InAppNotification::query()->where('type', 'sdi.send.failed')->count())->toBe(1);
    $this->assertDatabaseHas('sdi_outbound_submissions', [
        'fiscal_document_id' => $invoice->id,
        'status' => SdiSubmissionStatus::ProviderRejected->value,
        'active_document_lock' => null,
    ]);
});

test('openapi v1 provider rejection stores an operational message and error code on the document event', function () {
    config()->set('sdi.driver', 'openapi_v1');
    config()->set('fe-openapi.sandbox_url', 'https://test.sdi.openapi.it');

    $settings = app(OpenApiSettings::class);
    $settings->api_token = 'test-token';
    $settings->sandbox = true;
    $settings->save();

    Http::fake([
        'https://test.sdi.openapi.it/invoices' => Http::response([
            'success' => false,
            'message' => 'An error occurred: fattura_elettronica_header.dati_trasmissione.codice_destinatario: This value should not be blank.',
            'error' => 230,
            'data' => null,
        ], 400),
    ]);

    $user = User::factory()->create(['is_admin' => true]);
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

    $this->actingAs($user)->postJson("/sell-invoices/{$invoice->id}/send-sdi")
        ->assertOk()
        ->assertJsonPath('success', true);

    $event = DocumentEvent::query()
        ->where('fiscal_document_id', $invoice->id)
        ->where('event_type', 'sdi_sent')
        ->where('status', 'failed')
        ->sole();

    expect($event->title)->toBe('Invio SDI fallito')
        ->and($event->message)->toContain('controlli del provider')
        ->and($event->message)->toContain('codice_destinatario')
        ->and($event->error_message)->toBe('230');

    $log = EiOutboundLog::query()
        ->where('fiscal_document_id', $invoice->id)
        ->where('event_type', 'send_failed')
        ->sole();

    expect($log->raw_payload)->toMatchArray([
        'provider_error_code' => '230',
        'provider_http_status' => 400,
    ]);

    $this->assertDatabaseHas('sdi_outbound_submissions', [
        'fiscal_document_id' => $invoice->id,
        'provider' => 'openapi_v1',
        'status' => SdiSubmissionStatus::ProviderRejected->value,
        'provider_error_code' => '230',
        'provider_http_status' => 400,
    ]);
});

test('a retry after an ambiguous provider outcome does not invoke the provider twice', function () {
    $user = User::factory()->create(['is_admin' => true]);
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
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->actingAs($user)->postJson("/sell-invoices/{$invoice->id}/send-sdi")
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    $this->assertDatabaseHas('sdi_outbound_submissions', [
        'fiscal_document_id' => $invoice->id,
        'status' => SdiSubmissionStatus::OutcomeUnknown->value,
        'active_document_lock' => (string) $invoice->id,
    ]);
    expect(InAppNotification::query()->where('type', 'sdi.send.unknown')->count())->toBe(1);
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
