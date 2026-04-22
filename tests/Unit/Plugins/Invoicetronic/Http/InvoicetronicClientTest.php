<?php

use Fatturino\FeInvoicetronic\Http\InvoicetronicException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Fatturino\FeInvoicetronic\Http\InvoicetronicClient;

it('sets Basic auth header with api key as username', function () {
    [$client, $mock] = mockInvoicetronicClient([
        jsonResponse(200, ['operation_left' => 10, 'signature_left' => 5]),
    ]);

    $client->getStatus();

    $authHeader = $mock->getLastRequest()->getHeaderLine('Authorization');
    $expected = 'Basic ' . base64_encode('ik_test_key:');

    expect($authHeader)->toBe($expected);
});

it('sets Accept application/json header', function () {
    [$client, $mock] = mockInvoicetronicClient([
        jsonResponse(200, ['operation_left' => 1, 'signature_left' => 1]),
    ]);

    $client->getStatus();

    expect($mock->getLastRequest()->getHeaderLine('Accept'))->toBe('application/json');
});

it('sets User-Agent header with plugin prefix', function () {
    [$client, $mock] = mockInvoicetronicClient([
        jsonResponse(200, ['operation_left' => 1, 'signature_left' => 1]),
    ]);

    $client->getStatus();

    expect($mock->getLastRequest()->getHeaderLine('User-Agent'))->toStartWith('fatturino-plugin/');
});

it('uses the configured base URL', function () {
    [$client, $mock] = mockInvoicetronicClient([
        jsonResponse(200, ['operation_left' => 1, 'signature_left' => 1]),
    ]);

    $client->getStatus();

    $uri = (string) $mock->getLastRequest()->getUri();
    expect($uri)->toStartWith('https://api.test/v1/status');
});

it('throws InvoicetronicException on 4xx with ProblemDetails body', function () {
    [$client] = mockInvoicetronicClient([
        problemResponse(422, 'Validation error', 'XML is not valid'),
    ]);

    expect(fn () => $client->getStatus())
        ->toThrow(InvoicetronicException::class);
});

it('parses title, status, detail from application/problem+json', function () {
    [$client] = mockInvoicetronicClient([
        problemResponse(422, 'Validation error', 'XML is not valid'),
    ]);

    try {
        $client->getStatus();
    } catch (InvoicetronicException $e) {
        expect($e->statusCode)->toBe(422)
            ->and($e->getTitle())->toBe('Validation error')
            ->and($e->getDetail())->toBe('XML is not valid');
    }
});

it('falls back to exception message when body is not JSON', function () {
    [$client] = mockInvoicetronicClient([
        new Response(500, ['Content-Type' => 'text/plain'], 'Internal server error'),
    ]);

    try {
        $client->getStatus();
    } catch (InvoicetronicException $e) {
        expect($e->statusCode)->toBe(500)
            ->and($e->getMessage())->toBe('HTTP 500');
    }
});

it('wraps ConnectException with status code 0', function () {
    $mock = new MockHandler([
        new ConnectException('Connection refused', new Request('GET', '/status')),
    ]);
    $stack = HandlerStack::create($mock);
    $client = new InvoicetronicClient('ik_test_key', 'https://api.test/v1', $stack);

    try {
        $client->getStatus();
    } catch (InvoicetronicException $e) {
        expect($e->statusCode)->toBe(0);
    }
});

it('throws InvoicetronicException on 5xx', function () {
    [$client] = mockInvoicetronicClient([
        problemResponse(500, 'Internal Server Error', 'Unexpected failure'),
    ]);

    expect(fn () => $client->getStatus())
        ->toThrow(InvoicetronicException::class);
});
