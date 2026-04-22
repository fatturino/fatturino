<?php

use Fatturino\FeInvoicetronic\Http\InvoicetronicClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

/**
 * Build a mocked InvoicetronicClient and the MockHandler.
 *
 * Use $mock->getLastRequest() to inspect the most recently sent request.
 * Queue multiple responses for tests that make more than one HTTP call.
 *
 * @param Response[] $responses Queued Guzzle responses
 * @return array{InvoicetronicClient, MockHandler} [$client, $mock]
 */
function mockInvoicetronicClient(array $responses): array
{
    $mock = new MockHandler($responses);
    $stack = HandlerStack::create($mock);

    $client = new InvoicetronicClient(
        apiKey: 'ik_test_key',
        baseUrl: 'https://api.test/v1',
        handlerStack: $stack,
    );

    return [$client, $mock];
}

function jsonResponse(int $status, array $body): Response
{
    return new Response($status, ['Content-Type' => 'application/json'], json_encode($body));
}

function problemResponse(int $status, string $title, string $detail = ''): Response
{
    return new Response($status, ['Content-Type' => 'application/problem+json'], json_encode([
        'title' => $title,
        'status' => $status,
        'detail' => $detail,
    ]));
}
