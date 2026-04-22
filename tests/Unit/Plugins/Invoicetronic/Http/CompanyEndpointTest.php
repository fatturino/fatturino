<?php

use Fatturino\FeInvoicetronic\Http\Dto\CompanyInfo;
use Fatturino\FeInvoicetronic\Http\InvoicetronicException;

it('finds company by VAT and returns CompanyInfo', function () {
    [$client] = mockInvoicetronicClient([
        jsonResponse(200, ['id' => 7, 'vat' => 'IT01234567890', 'fiscal_code' => '01234567890', 'name' => 'Acme Srl']),
    ]);

    $company = $client->findCompanyByVat('IT01234567890');

    expect($company)->toBeInstanceOf(CompanyInfo::class)
        ->and($company->id)->toBe(7)
        ->and($company->vat)->toBe('IT01234567890')
        ->and($company->name)->toBe('Acme Srl');
});

it('sends GET request to /company/{vat}', function () {
    [$client, $mock] = mockInvoicetronicClient([
        jsonResponse(200, ['id' => 1, 'vat' => 'IT00000000000', 'fiscal_code' => '00000000000', 'name' => 'Test']),
    ]);

    $client->findCompanyByVat('IT00000000000');

    expect((string) $mock->getLastRequest()->getUri())->toEndWith('/company/IT00000000000');
});

it('returns null when company VAT returns 404', function () {
    [$client] = mockInvoicetronicClient([
        problemResponse(404, 'Not Found', 'Company not found'),
    ]);

    $result = $client->findCompanyByVat('IT99999999999');

    expect($result)->toBeNull();
});

it('throws on non-404 errors when looking up company', function () {
    [$client] = mockInvoicetronicClient([
        problemResponse(500, 'Server Error'),
    ]);

    expect(fn () => $client->findCompanyByVat('IT00000000000'))
        ->toThrow(InvoicetronicException::class);
});

it('creates company with vat, fiscal_code, name and returns CompanyInfo', function () {
    [$client, $mock] = mockInvoicetronicClient([
        jsonResponse(201, ['id' => 3, 'vat' => 'IT01234567890', 'fiscal_code' => '01234567890', 'name' => 'Nuova Srl']),
    ]);

    $company = $client->registerCompany('IT01234567890', '01234567890', 'Nuova Srl');

    expect($company)->toBeInstanceOf(CompanyInfo::class)
        ->and($company->id)->toBe(3);

    $body = json_decode((string) $mock->getLastRequest()->getBody(), true);
    expect($body['vat'])->toBe('IT01234567890')
        ->and($body['name'])->toBe('Nuova Srl');
});

it('throws InvoicetronicException on 422 company creation', function () {
    [$client] = mockInvoicetronicClient([
        problemResponse(422, 'Unprocessable', 'VAT already registered'),
    ]);

    expect(fn () => $client->registerCompany('IT01234567890', '01234567890', 'Test'))
        ->toThrow(InvoicetronicException::class);
});
