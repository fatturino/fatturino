<?php

use Fatturino\FeInvoicetronic\Http\Dto\SendResult;
use Fatturino\FeInvoicetronic\Http\Enums\SignatureMode;
use Fatturino\FeInvoicetronic\Http\InvoicetronicException;

const SAMPLE_XML = '<?xml version="1.0"?><FatturaElettronica></FatturaElettronica>';

it('sends XML payload with application/xml content-type', function () {
    [$client, $mock] = mockInvoicetronicClient([
        jsonResponse(201, ['id' => 99, 'identifier' => 'IT01234567890_00001', 'file_name' => 'IT01234567890_00001.xml']),
    ]);

    $client->sendXml(SAMPLE_XML);

    $request = $mock->getLastRequest();
    expect($request->getHeaderLine('Content-Type'))->toContain('application/xml')
        ->and((string) $request->getBody())->toBe(SAMPLE_XML);
});

it('does NOT write temp files to disk', function () {
    [$client] = mockInvoicetronicClient([
        jsonResponse(201, ['id' => 1, 'identifier' => 'IT00000000000_00001', 'file_name' => 'test.xml']),
    ]);

    $tempFiles = glob(sys_get_temp_dir() . '/IT*.xml') ?: [];
    $countBefore = count($tempFiles);

    $client->sendXml(SAMPLE_XML);

    $tempFiles = glob(sys_get_temp_dir() . '/IT*.xml') ?: [];
    expect(count($tempFiles))->toBe($countBefore);
});

it('sends to POST /send/xml endpoint', function () {
    [$client, $mock] = mockInvoicetronicClient([
        jsonResponse(201, ['id' => 1, 'identifier' => 'IT00000000000_00001', 'file_name' => 'test.xml']),
    ]);

    $client->sendXml(SAMPLE_XML);

    expect($mock->getLastRequest()->getMethod())->toBe('POST')
        ->and((string) $mock->getLastRequest()->getUri())->toContain('/send/xml');
});

it('passes validate=true query when requested', function () {
    [$client, $mock] = mockInvoicetronicClient([
        jsonResponse(201, ['id' => 1, 'identifier' => 'test', 'file_name' => 'test.xml']),
    ]);

    $client->sendXml(SAMPLE_XML, validate: true);

    $uri = (string) $mock->getLastRequest()->getUri();
    expect($uri)->toContain('validate=true');
});

it('does not add validate query param by default', function () {
    [$client, $mock] = mockInvoicetronicClient([
        jsonResponse(201, ['id' => 1, 'identifier' => 'test', 'file_name' => 'test.xml']),
    ]);

    $client->sendXml(SAMPLE_XML);

    $uri = (string) $mock->getLastRequest()->getUri();
    expect($uri)->not()->toContain('validate=');
});

it('passes signature query param when non-Auto', function () {
    [$client, $mock] = mockInvoicetronicClient([
        jsonResponse(201, ['id' => 1, 'identifier' => 'test', 'file_name' => 'test.xml']),
    ]);

    $client->sendXml(SAMPLE_XML, signature: SignatureMode::None);

    $uri = (string) $mock->getLastRequest()->getUri();
    expect($uri)->toContain('signature=None');
});

it('returns SendResult with id, identifier, file_name', function () {
    [$client] = mockInvoicetronicClient([
        jsonResponse(201, ['id' => 55, 'identifier' => 'IT01234567890_00005', 'file_name' => 'IT01234567890_00005.xml']),
    ]);

    $result = $client->sendXml(SAMPLE_XML);

    expect($result)->toBeInstanceOf(SendResult::class)
        ->and($result->id)->toBe(55)
        ->and($result->identifier)->toBe('IT01234567890_00005')
        ->and($result->fileName)->toBe('IT01234567890_00005.xml');
});

it('surfaces 422 validation errors as InvoicetronicException', function () {
    [$client] = mockInvoicetronicClient([
        problemResponse(422, 'Validation failed', 'Invalid XML structure'),
    ]);

    try {
        $client->sendXml(SAMPLE_XML);
        fail('Expected InvoicetronicException');
    } catch (InvoicetronicException $e) {
        expect($e->statusCode)->toBe(422)
            ->and($e->getTitle())->toBe('Validation failed');
    }
});
