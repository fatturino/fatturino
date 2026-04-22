<?php

use Fatturino\FeInvoicetronic\Http\Dto\SendResult;
use Fatturino\FeInvoicetronic\Http\Dto\StatusInfo;
use Fatturino\FeInvoicetronic\Http\Dto\UpdateItem;
use Fatturino\FeInvoicetronic\Http\Dto\CompanyInfo;

it('SendResult::fromArray casts id to int and preserves strings', function () {
    $result = SendResult::fromArray(['id' => '42', 'identifier' => 'IT001_001', 'file_name' => 'IT001_001.xml']);

    expect($result->id)->toBe(42)
        ->and($result->identifier)->toBe('IT001_001')
        ->and($result->fileName)->toBe('IT001_001.xml');
});

it('StatusInfo::fromArray casts integers', function () {
    $status = StatusInfo::fromArray(['operation_left' => '10', 'signature_left' => '5']);

    expect($status->operationLeft)->toBe(10)
        ->and($status->signatureLeft)->toBe(5);
});

it('UpdateItem::fromArray maps state as string', function () {
    $update = UpdateItem::fromArray([
        'id' => 1,
        'send_id' => 10,
        'state' => 'Consegnato',
        'is_read' => false,
    ]);

    expect($update->state)->toBe('Consegnato');
});

it('CompanyInfo::fromArray handles all fields', function () {
    $company = CompanyInfo::fromArray([
        'id' => 3,
        'vat' => 'IT01234567890',
        'fiscal_code' => '01234567890',
        'name' => 'Test Srl',
    ]);

    expect($company->id)->toBe(3)
        ->and($company->vat)->toBe('IT01234567890')
        ->and($company->fiscalCode)->toBe('01234567890')
        ->and($company->name)->toBe('Test Srl');
});
