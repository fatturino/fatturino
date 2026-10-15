<?php

use App\Services\OpenApiSdiService;
use App\Settings\CompanySettings;
use App\Settings\OpenApiSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $companySettings = app(CompanySettings::class);
    $companySettings->company_vat_number = '12345678903';
    $companySettings->save();

    $settings = app(OpenApiSettings::class);
    $settings->api_token = 'test-token';
    $settings->sandbox = true;
    $settings->save();
});

test('send invoice treats connection failures as an unknown outcome', function () {
    Http::fake(function () {
        throw new ConnectionException('timeout');
    });

    $service = app(OpenApiSdiService::class);

    $result = $service->sendInvoice('<xml />');

    expect($result['success'])->toBeFalse()
        ->and($result['outcome_unknown'])->toBeTrue()
        ->and($result['error_message'])->toBe('Non è stato possibile determinare l’esito dell’invio.');
});

test('send invoice treats server failures as an unknown outcome', function () {
    Http::fake([
        'https://test.sdi.openapi.it/invoices' => Http::response(['message' => 'temporary outage'], 500),
    ]);

    $result = app(OpenApiSdiService::class)->sendInvoice('<xml />');

    expect($result['success'])->toBeFalse()
        ->and($result['outcome_unknown'])->toBeTrue()
        ->and($result['error_message'])->toBe('Non è stato possibile determinare l’esito dell’invio.');
});

test('send invoice classifies documented validation rejection as definitive', function () {
    Http::fake([
        'https://test.sdi.openapi.it/invoices' => Http::response([
            'success' => false,
            'error' => 'invalid_invoice',
            'message' => 'Invoice validation failed',
        ], 422),
    ]);

    $result = app(OpenApiSdiService::class)->sendInvoice('<xml />');

    expect($result['success'])->toBeFalse()
        ->and($result['outcome_unknown'])->toBeFalse()
        ->and($result['error_code'])->toBe('invalid_invoice')
        ->and($result['error_message'])->toBe('Invoice validation failed');
});

test('send invoice treats malformed error responses as an unknown outcome', function () {
    Http::fake([
        'https://test.sdi.openapi.it/invoices' => Http::response('not-json', 422, ['Content-Type' => 'text/plain']),
    ]);

    $result = app(OpenApiSdiService::class)->sendInvoice('<xml />');

    expect($result['success'])->toBeFalse()
        ->and($result['outcome_unknown'])->toBeTrue();
});

test('get supplier invoices uses recipient and sender query params from the spec', function () {
    Http::fake([
        'https://test.sdi.openapi.it/invoices*' => Http::response([
            'data' => [],
            'meta' => ['current_page' => 1, 'last_page' => 1],
        ], 200),
    ]);

    $service = app(OpenApiSdiService::class);

    $result = $service->getSupplierInvoices([
        'sender' => '98765432109',
        'page' => 2,
        'per_page' => 50,
    ]);

    expect($result['success'])->toBeTrue();

    Http::assertSent(function ($request) {
        $query = [];
        parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);

        return str_starts_with($request->url(), 'https://test.sdi.openapi.it/invoices')
            && ($query['type'] ?? null) === '1'
            && ($query['recipient'] ?? null) === '12345678903'
            && ($query['sender'] ?? null) === '98765432109'
            && ($query['page'] ?? null) === '2'
            && ($query['per_page'] ?? null) === '50'
            && ! array_key_exists('destinatario', $query)
            && ! array_key_exists('mittente', $query);
    });
});

test('get customer invoices scopes active invoices to company vat as sender', function () {
    Http::fake([
        'https://test.sdi.openapi.it/invoices*' => Http::response([
            'data' => [],
            'meta' => ['current_page' => 1, 'last_page' => 1],
        ], 200),
    ]);

    $service = app(OpenApiSdiService::class);

    $result = $service->getCustomerInvoices([
        'recipient' => '98765432109',
        'page' => 2,
        'per_page' => 50,
    ]);

    expect($result['success'])->toBeTrue();

    Http::assertSent(function ($request) {
        $query = [];
        parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);

        return str_starts_with($request->url(), 'https://test.sdi.openapi.it/invoices')
            && ($query['type'] ?? null) === '0'
            && ($query['sender'] ?? null) === '12345678903'
            && ($query['recipient'] ?? null) === '98765432109'
            && ($query['page'] ?? null) === '2'
            && ($query['per_page'] ?? null) === '50';
    });
});

test('webhook simulation returns safe provider diagnostics on validation failure', function () {
    Http::fake([
        'https://test.sdi.openapi.it/simulate/supplier-invoice' => Http::response([
            'success' => false,
            'error' => '620',
            'message' => 'recipient fiscal id is required',
        ], 400),
    ]);

    $result = app(OpenApiSdiService::class)->simulateWebhookEvent('supplier-invoice', ['invalid' => true]);

    expect($result)->toMatchArray([
        'success' => false,
        'error' => '620',
        'message' => 'recipient fiscal id is required',
        'http_status' => 400,
    ]);
});
