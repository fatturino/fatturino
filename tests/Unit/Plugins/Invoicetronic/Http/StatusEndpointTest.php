<?php

use Fatturino\FeInvoicetronic\Http\Dto\StatusInfo;
use Fatturino\FeInvoicetronic\Http\InvoicetronicException;

it('returns StatusInfo with operation_left and signature_left', function () {
    [$client] = mockInvoicetronicClient([
        jsonResponse(200, ['operation_left' => 42, 'signature_left' => 10]),
    ]);

    $status = $client->getStatus();

    expect($status)->toBeInstanceOf(StatusInfo::class)
        ->and($status->operationLeft)->toBe(42)
        ->and($status->signatureLeft)->toBe(10);
});

it('sends GET request to /status', function () {
    [$client, $mock] = mockInvoicetronicClient([
        jsonResponse(200, ['operation_left' => 1, 'signature_left' => 1]),
    ]);

    $client->getStatus();

    expect($mock->getLastRequest()->getMethod())->toBe('GET')
        ->and((string) $mock->getLastRequest()->getUri())->toEndWith('/status');
});

it('throws InvoicetronicException on 401 invalid api key', function () {
    [$client] = mockInvoicetronicClient([
        problemResponse(401, 'Unauthorized', 'Invalid API key'),
    ]);

    try {
        $client->getStatus();
        fail('Expected InvoicetronicException');
    } catch (InvoicetronicException $e) {
        expect($e->statusCode)->toBe(401)
            ->and($e->getTitle())->toBe('Unauthorized');
    }
});
