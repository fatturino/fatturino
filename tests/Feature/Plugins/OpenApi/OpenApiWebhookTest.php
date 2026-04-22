<?php

use App\Enums\SdiStatus;
use App\Models\Invoice;
use App\Models\PurchaseInvoice;
use App\Models\SdiLog;
use App\Services\InvoiceXmlImportService;
use App\Settings\InvoiceSettings;
use Fatturino\FeOpenApi\OpenApiSdiService;
use Fatturino\FeOpenApi\Settings\OpenApiSettings;
use Illuminate\Support\Facades\Http;

// Helper to build a valid Bearer token header
function openApiWebhookHeaders(string $secret = 'test_webhook_secret'): array
{
    return ['Authorization' => "Bearer {$secret}"];
}

beforeEach(function () {
    $settings = app(OpenApiSettings::class);
    $settings->webhook_secret = 'test_webhook_secret';
    $settings->api_token = 'test_api_token';
    $settings->sandbox = true;
    $settings->activated = true;
    $settings->company_sdi_code = '';
    $settings->webhook_url = '';
    $settings->save();
});

// ─── Auth ────────────────────────────────────────────────────────────────────

it('returns 401 without authorization header', function () {
    $this->postJson('/api/openapi/webhook', ['event' => 'supplier-invoice'])
        ->assertStatus(401);
});

it('returns 401 with wrong bearer token', function () {
    $this->postJson('/api/openapi/webhook', ['event' => 'supplier-invoice'], [
        'Authorization' => 'Bearer wrong_token',
    ])->assertStatus(401);
});

// ─── Supplier invoice (passive) ───────────────────────────────────────────────

it('returns 200 with duplicate status when supplier invoice already imported', function () {
    $uuid = 'test-uuid-1234-abcd-efgh';

    PurchaseInvoice::factory()->create(['sdi_uuid' => $uuid]);

    $payload = [
        'event' => 'supplier-invoice',
        'data' => ['invoice' => ['uuid' => $uuid]],
    ];

    $this->postJson('/api/openapi/webhook', $payload, openApiWebhookHeaders())
        ->assertStatus(200)
        ->assertJson(['status' => 'duplicate']);

    // No additional invoice should be created
    expect(PurchaseInvoice::withoutGlobalScopes()->where('sdi_uuid', $uuid)->count())->toBe(1);
});

it('returns 200 error when supplier invoice UUID is missing', function () {
    $payload = ['event' => 'supplier-invoice', 'data' => ['invoice' => []]];

    $this->postJson('/api/openapi/webhook', $payload, openApiWebhookHeaders())
        ->assertStatus(200)
        ->assertJson(['status' => 'error']);
});

it('returns 200 skipped when no default purchase sequence configured', function () {
    $uuid = 'test-uuid-no-sequence';

    // Mock the OpenAPI HTTP download call
    Http::fake([
        '*/invoices/*/xml' => Http::response('<FatturaElettronica/>', 200, ['Content-Type' => 'application/xml']),
    ]);

    // No default purchase sequence
    $invoiceSettings = app(InvoiceSettings::class);
    $invoiceSettings->default_sequence_purchase = null;
    $invoiceSettings->save();

    $payload = [
        'event' => 'supplier-invoice',
        'data' => ['invoice' => ['uuid' => $uuid]],
    ];

    $this->postJson('/api/openapi/webhook', $payload, openApiWebhookHeaders())
        ->assertStatus(200)
        ->assertJson(['status' => 'skipped']);
});

it('imports and tags purchase invoice with sdi_uuid on first webhook delivery', function () {
    $uuid = 'new-uuid-to-import-5678';

    // Mock HTTP download
    Http::fake([
        '*/invoices/*/xml' => Http::response('<FatturaElettronica/>', 200, ['Content-Type' => 'application/xml']),
    ]);

    // Create a sequence so the import does not skip
    $sequence = \App\Models\Sequence::factory()->create(['type' => 'purchase']);
    $invoiceSettings = app(InvoiceSettings::class);
    $invoiceSettings->default_sequence_purchase = $sequence->id;
    $invoiceSettings->save();

    // Mock InvoiceXmlImportService to avoid real XML parsing,
    // but simulate it creating a PurchaseInvoice
    $fakeInvoice = PurchaseInvoice::factory()->create(['sdi_uuid' => null]);

    $this->mock(InvoiceXmlImportService::class, function ($mock) {
        $mock->shouldReceive('importXml')->once();
    });

    $payload = [
        'event' => 'supplier-invoice',
        'data' => ['invoice' => ['uuid' => $uuid]],
    ];

    $this->postJson('/api/openapi/webhook', $payload, openApiWebhookHeaders())
        ->assertStatus(200)
        ->assertJson(['status' => 'ok']);

    // The latest PurchaseInvoice (fakeInvoice) should be tagged with the UUID
    $fakeInvoice->refresh();
    expect($fakeInvoice->sdi_uuid)->toBe($uuid);
});

// ─── Customer notification ────────────────────────────────────────────────────

it('updates sdi_status on NS customer notification', function () {
    $uuid = 'active-invoice-uuid-1234';
    $invoice = Invoice::factory()->create(['sdi_uuid' => $uuid, 'sdi_status' => SdiStatus::Sent->value]);

    $payload = [
        'event' => 'customer-notification',
        'data' => [
            'notification' => [
                'invoice_uuid' => $uuid,
                'type' => 'NS',
            ],
        ],
    ];

    $this->postJson('/api/openapi/webhook', $payload, openApiWebhookHeaders())
        ->assertStatus(200)
        ->assertJson(['status' => 'ok']);

    $invoice->refresh();
    expect($invoice->sdi_status)->toBe(SdiStatus::Rejected);
});

it('creates SdiLog entry on customer notification', function () {
    $uuid = 'invoice-for-sdi-log';
    Invoice::factory()->create(['sdi_uuid' => $uuid, 'sdi_status' => SdiStatus::Sent->value]);

    $payload = [
        'event' => 'customer-notification',
        'data' => [
            'notification' => ['invoice_uuid' => $uuid, 'type' => 'RC'],
        ],
    ];

    $this->postJson('/api/openapi/webhook', $payload, openApiWebhookHeaders())
        ->assertStatus(200);

    expect(SdiLog::where('event_type', 'RC')->exists())->toBeTrue();
});

it('returns 200 even if invoice not found for notification', function () {
    $payload = [
        'event' => 'customer-notification',
        'data' => [
            'notification' => ['invoice_uuid' => 'non-existent-uuid', 'type' => 'NS'],
        ],
    ];

    $this->postJson('/api/openapi/webhook', $payload, openApiWebhookHeaders())
        ->assertStatus(200);
});

// ─── Unknown event ────────────────────────────────────────────────────────────

it('returns 200 ignored for unknown event types', function () {
    $payload = ['event' => 'unknown-event-type', 'data' => []];

    $this->postJson('/api/openapi/webhook', $payload, openApiWebhookHeaders())
        ->assertStatus(200)
        ->assertJson(['status' => 'ignored']);
});
