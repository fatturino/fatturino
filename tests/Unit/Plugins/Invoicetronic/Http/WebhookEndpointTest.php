<?php

use Fatturino\FeInvoicetronic\Http\Dto\WebHookInfo;
use Fatturino\FeInvoicetronic\Http\InvoicetronicException;
use GuzzleHttp\Psr7\Response;

it('creates webhook with url, events, description', function () {
    [$client, $mock] = mockInvoicetronicClient([
        jsonResponse(201, [
            'id' => 5,
            'url' => 'https://example.com/webhook',
            'description' => 'Test webhook',
            'enabled' => true,
            'events' => ['receive.add', 'update.add'],
            'secret' => 'whsec_abc123',
        ]),
    ]);

    $webhook = $client->createWebhook('https://example.com/webhook', ['receive.add', 'update.add'], 'Test webhook');

    expect($webhook)->toBeInstanceOf(WebHookInfo::class)
        ->and($webhook->id)->toBe(5)
        ->and($webhook->secret)->toBe('whsec_abc123');

    $body = json_decode((string) $mock->getLastRequest()->getBody(), true);
    expect($body['url'])->toBe('https://example.com/webhook')
        ->and($body['enabled'])->toBeTrue()
        ->and($body['events'])->toBe(['receive.add', 'update.add']);
});

it('sends POST to /webhook', function () {
    [$client, $mock] = mockInvoicetronicClient([
        jsonResponse(201, ['id' => 1, 'url' => 'https://test.com', 'description' => '', 'enabled' => true, 'events' => []]),
    ]);

    $client->createWebhook('https://test.com', [], '');

    expect($mock->getLastRequest()->getMethod())->toBe('POST')
        ->and((string) $mock->getLastRequest()->getUri())->toEndWith('/webhook');
});

it('deletes webhook by id returning 200', function () {
    [$client, $mock] = mockInvoicetronicClient([
        jsonResponse(200, ['id' => 5, 'url' => 'https://test.com', 'description' => '', 'enabled' => false, 'events' => []]),
    ]);

    $client->deleteWebhook(5);

    expect($mock->getLastRequest()->getMethod())->toBe('DELETE')
        ->and((string) $mock->getLastRequest()->getUri())->toEndWith('/webhook/5');
});

it('throws InvoicetronicException when deleting missing webhook', function () {
    [$client] = mockInvoicetronicClient([
        problemResponse(404, 'Not Found', 'Webhook not found'),
    ]);

    expect(fn () => $client->deleteWebhook(999))
        ->toThrow(InvoicetronicException::class);
});
