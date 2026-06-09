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

test('send invoice returns readable error on connection failure', function () {
    Http::fake(function () {
        throw new ConnectionException('timeout');
    });

    $service = app(OpenApiSdiService::class);

    $result = $service->sendInvoice('<xml />');

    expect($result['success'])->toBeFalse()
        ->and($result['error_message'])->toContain('Failed to connect to OpenAPI SDI:')
        ->and($result['error_message'])->toContain('timeout');
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
