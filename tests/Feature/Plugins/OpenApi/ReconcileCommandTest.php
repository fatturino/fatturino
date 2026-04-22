<?php

use App\Models\PurchaseInvoice;
use Fatturino\FeOpenApi\Settings\OpenApiSettings;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $settings = app(OpenApiSettings::class);
    $settings->api_token = 'test_api_token';
    $settings->sandbox = true;
    $settings->activated = true;
    $settings->webhook_secret = '';
    $settings->company_sdi_code = '';
    $settings->webhook_url = '';
    $settings->save();
});

it('exits with failure when OpenAPI is not configured', function () {
    $settings = app(OpenApiSettings::class);
    $settings->api_token = '';
    $settings->save();

    $this->artisan('openapi:reconcile')
        ->assertFailed();
});

it('shows empty table when no supplier invoices found', function () {
    Http::fake([
        '*/invoices*' => Http::response(['data' => [], 'meta' => null], 200),
    ]);
    Http::fake([
        '*/notifications*' => Http::response(['data' => [], 'meta' => null], 200),
    ]);

    $this->artisan('openapi:reconcile --dry-run')
        ->assertSuccessful();
});

it('skips already imported supplier invoices by sdi_uuid', function () {
    $uuid = 'already-imported-uuid';

    Http::fake([
        '*/invoices*' => Http::response([
            'data' => [
                ['uuid' => $uuid, 'created_at' => now()->toIso8601String(), 'sdi_file_name' => 'test.xml'],
            ],
            'meta' => null,
        ], 200),
        '*/notifications*' => Http::response(['data' => []], 200),
    ]);

    PurchaseInvoice::factory()->create(['sdi_uuid' => $uuid]);

    $initialCount = PurchaseInvoice::withoutGlobalScopes()->count();

    $this->artisan('openapi:reconcile --receive-only')
        ->assertSuccessful();

    // No new invoices should be created
    expect(PurchaseInvoice::withoutGlobalScopes()->count())->toBe($initialCount);
});

it('does not write anything in dry-run mode', function () {
    $uuid = 'new-invoice-uuid-dry-run';

    Http::fake([
        '*/invoices*' => Http::response([
            'data' => [
                ['uuid' => $uuid, 'created_at' => now()->toIso8601String(), 'sdi_file_name' => 'fattura.xml'],
            ],
            'meta' => null,
        ], 200),
        '*/notifications*' => Http::response(['data' => []], 200),
    ]);

    $initialCount = PurchaseInvoice::withoutGlobalScopes()->count();

    $this->artisan('openapi:reconcile --receive-only --dry-run')
        ->assertSuccessful()
        ->expectsOutputToContain('[DRY RUN]');

    // Dry-run must not create any records
    expect(PurchaseInvoice::withoutGlobalScopes()->count())->toBe($initialCount);
});

it('skips invoices older than the --days cutoff', function () {
    Http::fake([
        '*/invoices*' => Http::response([
            'data' => [
                [
                    'uuid' => 'old-invoice-uuid',
                    'created_at' => now()->subDays(30)->toIso8601String(),
                    'sdi_file_name' => 'old.xml',
                ],
            ],
            'meta' => null,
        ], 200),
        '*/notifications*' => Http::response(['data' => []], 200),
    ]);

    $initialCount = PurchaseInvoice::withoutGlobalScopes()->count();

    $this->artisan('openapi:reconcile --receive-only --days=7')
        ->assertSuccessful();

    expect(PurchaseInvoice::withoutGlobalScopes()->count())->toBe($initialCount);
});

it('passes type=1 filter for passive invoices to the API', function () {
    Http::fake([
        '*/invoices*' => Http::response(['data' => []], 200),
        '*/notifications*' => Http::response(['data' => []], 200),
    ]);

    $this->artisan('openapi:reconcile --receive-only --dry-run')
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'type=1');
    });
});

it('respects --max-pages guard and warns when limit reached', function () {
    // Return a full page every time to trigger the guard
    $fullPage = array_fill(0, 100, [
        'uuid' => 'uuid-' . uniqid(),
        'created_at' => now()->toIso8601String(),
        'sdi_file_name' => 'invoice.xml',
    ]);

    Http::fake([
        '*/invoices*' => Http::response(['data' => $fullPage], 200),
        '*/notifications*' => Http::response(['data' => []], 200),
        '*/invoices/*/xml' => Http::response('<FatturaElettronica/>', 200),
    ]);

    // With max-pages=1, only one page should be fetched and then warn
    $this->artisan('openapi:reconcile --receive-only --dry-run --max-pages=1')
        ->assertSuccessful()
        ->expectsOutputToContain('max-pages');
});
