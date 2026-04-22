<?php

use Fatturino\FeInvoicetronic\Http\InvoicetronicClient;
use Fatturino\FeInvoicetronic\Http\InvoicetronicException;
use Fatturino\FeInvoicetronic\Http\Dto\SendResult;
use Fatturino\FeInvoicetronic\Http\Dto\StatusInfo;
use Fatturino\FeInvoicetronic\Http\Dto\CompanyInfo;
use Fatturino\FeInvoicetronic\Http\Dto\WebHookInfo;
use Fatturino\FeInvoicetronic\Http\Dto\ReceivePage;
use Fatturino\FeInvoicetronic\Http\Dto\ReceiveItem;
use Fatturino\FeInvoicetronic\Http\Dto\UpdatePage;
use Fatturino\FeInvoicetronic\Http\Dto\UpdateItem;
use Fatturino\FeInvoicetronic\InvoicetronicSdiService;
use Fatturino\FeInvoicetronic\Settings\InvoicetronicSettings;
use Mockery\MockInterface;

function makeService(MockInterface $mockClient): InvoicetronicSdiService
{
    $settings = Mockery::mock(InvoicetronicSettings::class);
    $settings->api_key = 'ik_test_key';
    $settings->company_id = 0;
    $settings->webhook_id = 0;
    $settings->webhook_secret = '';
    $settings->webhook_url = '';
    $settings->activated = true;

    $service = new InvoicetronicSdiService($settings);

    // Inject the mock client via reflection
    $reflection = new ReflectionProperty(InvoicetronicSdiService::class, 'client');
    $reflection->setAccessible(true);
    $reflection->setValue($service, $mockClient);

    return $service;
}

it('sendInvoice returns success with uuid on 201', function () {
    $mockClient = Mockery::mock(InvoicetronicClient::class);
    $mockClient->shouldReceive('sendXml')
        ->once()
        ->andReturn(new SendResult(id: 42, identifier: 'IT001_001', fileName: 'IT001_001.xml'));

    $service = makeService($mockClient);
    $result = $service->sendInvoice('<xml/>');

    expect($result['success'])->toBeTrue()
        ->and($result['uuid'])->toBe('42');
});

it('sendInvoice returns failure with error_message on exception', function () {
    $mockClient = Mockery::mock(InvoicetronicClient::class);
    $mockClient->shouldReceive('sendXml')
        ->once()
        ->andThrow(new InvoicetronicException('Validation failed', 422, null, ''));

    $service = makeService($mockClient);
    $result = $service->sendInvoice('<xml/>');

    expect($result['success'])->toBeFalse()
        ->and($result['error_message'])->toBe('Validation failed');
});

it('sendInvoice returns failure when not configured', function () {
    $settings = Mockery::mock(InvoicetronicSettings::class);
    $settings->api_key = '';
    $settings->company_id = 0;

    $service = new InvoicetronicSdiService($settings);
    $result = $service->sendInvoice('<xml/>');

    expect($result['success'])->toBeFalse();
});

it('checkAccountStatus returns operations_left and signatures_left', function () {
    $mockClient = Mockery::mock(InvoicetronicClient::class);
    $mockClient->shouldReceive('getStatus')
        ->once()
        ->andReturn(new StatusInfo(operationLeft: 10, signatureLeft: 3));

    $service = makeService($mockClient);
    $result = $service->checkAccountStatus();

    expect($result['success'])->toBeTrue()
        ->and($result['operations_left'])->toBe(10)
        ->and($result['signatures_left'])->toBe(3);
});

it('findCompanyByVat returns null on 404', function () {
    $mockClient = Mockery::mock(InvoicetronicClient::class);
    $mockClient->shouldReceive('findCompanyByVat')
        ->once()
        ->andReturn(null);

    $service = makeService($mockClient);
    $result = $service->findCompanyByVat('IT01234567890');

    expect($result['found'])->toBeFalse();
});

it('findCompanyByVat normalizes VAT by adding IT prefix', function () {
    $mockClient = Mockery::mock(InvoicetronicClient::class);
    $mockClient->shouldReceive('findCompanyByVat')
        ->with('IT01234567890')
        ->once()
        ->andReturn(new CompanyInfo(id: 1, vat: 'IT01234567890', fiscalCode: '01234567890', name: 'Test'));

    $service = makeService($mockClient);
    $service->findCompanyByVat('01234567890'); // no prefix
});

it('createWebhook stores correct events and description', function () {
    $mockClient = Mockery::mock(InvoicetronicClient::class);
    $mockClient->shouldReceive('createWebhook')
        ->with('https://example.com/hook', ['receive.add', 'update.add'], 'Fatturino SDI webhook')
        ->once()
        ->andReturn(new WebHookInfo(id: 5, url: 'https://example.com/hook', description: '', enabled: true, events: [], secret: 'sec123'));

    $service = makeService($mockClient);
    $result = $service->createWebhook('https://example.com/hook');

    expect($result['success'])->toBeTrue()
        ->and($result['webhook_id'])->toBe(5)
        ->and($result['secret'])->toBe('sec123');
});

it('getSupplierInvoices paginates correctly', function () {
    $items = [
        new ReceiveItem(1, 'IT001_001', 'IT00000000001', 'Acme', 'a.xml', '2024-01-01', false, null),
    ];
    $page = new ReceivePage($items, 2, 50);

    $mockClient = Mockery::mock(InvoicetronicClient::class);
    $mockClient->shouldReceive('listReceive')
        ->once()
        ->andReturn($page);

    $service = makeService($mockClient);
    $result = $service->getSupplierInvoices(['page' => 2, 'per_page' => 50]);

    expect($result['success'])->toBeTrue()
        ->and($result['data'])->toHaveCount(1);
});

afterEach(function () {
    Mockery::close();
});
