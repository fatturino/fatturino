<?php

use Fatturino\FeInvoicetronic\Http\InvoicetronicException;
use GuzzleHttp\Psr7\Response;

it('returns true on 204 No Content', function () {
    [$client] = mockInvoicetronicClient([
        new Response(204),
    ]);

    $result = $client->validateXml('<?xml version="1.0"?><test/>');

    expect($result)->toBeTrue();
});

it('sends to POST /send/validate/xml', function () {
    [$client, $mock] = mockInvoicetronicClient([
        new Response(204),
    ]);

    $client->validateXml('<?xml version="1.0"?><test/>');

    expect((string) $mock->getLastRequest()->getUri())->toContain('/send/validate/xml');
});

it('sends XML body with application/xml content-type', function () {
    [$client, $mock] = mockInvoicetronicClient([
        new Response(204),
    ]);

    $xml = '<?xml version="1.0"?><test/>';
    $client->validateXml($xml);

    expect($mock->getLastRequest()->getHeaderLine('Content-Type'))->toContain('application/xml')
        ->and((string) $mock->getLastRequest()->getBody())->toBe($xml);
});

it('throws InvoicetronicException on 422 validation error', function () {
    [$client] = mockInvoicetronicClient([
        problemResponse(422, 'XML is invalid', 'Missing required field'),
    ]);

    expect(fn () => $client->validateXml('<bad/>'))
        ->toThrow(InvoicetronicException::class);
});
