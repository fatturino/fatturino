<?php

use Fatturino\FeInvoicetronic\Http\Dto\ReceiveItem;
use Fatturino\FeInvoicetronic\Http\Dto\ReceivePage;

it('lists receive items returning a ReceivePage', function () {
    [$client] = mockInvoicetronicClient([
        jsonResponse(200, [
            ['id' => 1, 'identifier' => 'IT00000000001_00001', 'file_name' => 'a.xml', 'is_read' => false],
            ['id' => 2, 'identifier' => 'IT00000000001_00002', 'file_name' => 'b.xml', 'is_read' => true],
        ]),
    ]);

    $page = $client->listReceive();

    expect($page)->toBeInstanceOf(ReceivePage::class)
        ->and($page->items)->toHaveCount(2)
        ->and($page->items[0])->toBeInstanceOf(ReceiveItem::class)
        ->and($page->items[0]->id)->toBe(1);
});

it('sends GET to /receive with default pagination params', function () {
    [$client, $mock] = mockInvoicetronicClient([
        jsonResponse(200, []),
    ]);

    $client->listReceive();

    $uri = (string) $mock->getLastRequest()->getUri();
    expect($uri)->toContain('page=1')
        ->and($uri)->toContain('page_size=100');
});

it('passes custom page_size and sort when provided', function () {
    [$client, $mock] = mockInvoicetronicClient([
        jsonResponse(200, []),
    ]);

    $client->listReceive(page: 2, pageSize: 50);

    $uri = (string) $mock->getLastRequest()->getUri();
    expect($uri)->toContain('page=2')
        ->and($uri)->toContain('page_size=50');
});

it('fetches single receive item by id with GET /receive/{id}', function () {
    [$client, $mock] = mockInvoicetronicClient([
        jsonResponse(200, ['id' => 42, 'identifier' => 'IT00000000001_00042', 'file_name' => 'c.xml', 'is_read' => false]),
    ]);

    $item = $client->getReceive(42);

    expect($item)->toBeInstanceOf(ReceiveItem::class)
        ->and($item->id)->toBe(42)
        ->and((string) $mock->getLastRequest()->getUri())->toContain('/receive/42');
});

it('passes include_payload=true when requested', function () {
    [$client, $mock] = mockInvoicetronicClient([
        jsonResponse(200, ['id' => 1, 'identifier' => 'x', 'file_name' => 'x.xml', 'is_read' => false, 'payload' => '<xml/>']),
    ]);

    $client->getReceive(1, includePayload: true);

    $uri = (string) $mock->getLastRequest()->getUri();
    expect($uri)->toContain('include_payload=true');
});

it('detects last page when items count is less than page_size', function () {
    [$client] = mockInvoicetronicClient([
        jsonResponse(200, [
            ['id' => 1, 'identifier' => 'x', 'file_name' => 'x.xml', 'is_read' => false],
        ]),
    ]);

    $page = $client->listReceive(page: 1, pageSize: 100);

    expect($page->isLastPage())->toBeTrue();
});

it('detects empty page', function () {
    [$client] = mockInvoicetronicClient([
        jsonResponse(200, []),
    ]);

    $page = $client->listReceive();

    expect($page->isEmpty())->toBeTrue();
});
