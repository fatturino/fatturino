<?php

use Fatturino\FeInvoicetronic\Http\Dto\UpdateItem;
use Fatturino\FeInvoicetronic\Http\Dto\UpdatePage;

it('lists updates returning an UpdatePage', function () {
    [$client] = mockInvoicetronicClient([
        jsonResponse(200, [
            ['id' => 10, 'send_id' => 99, 'state' => 'Consegnato', 'is_read' => false],
            ['id' => 11, 'send_id' => 99, 'state' => 'AccettatoDalDestinatario', 'is_read' => true],
        ]),
    ]);

    $page = $client->listUpdates();

    expect($page)->toBeInstanceOf(UpdatePage::class)
        ->and($page->items)->toHaveCount(2)
        ->and($page->items[0])->toBeInstanceOf(UpdateItem::class)
        ->and($page->items[0]->state)->toBe('Consegnato');
});

it('filters by send_id', function () {
    [$client, $mock] = mockInvoicetronicClient([
        jsonResponse(200, []),
    ]);

    $client->listUpdates(sendId: 99);

    $uri = (string) $mock->getLastRequest()->getUri();
    expect($uri)->toContain('send_id=99');
});

it('filters by state', function () {
    [$client, $mock] = mockInvoicetronicClient([
        jsonResponse(200, []),
    ]);

    $client->listUpdates(state: 'Consegnato');

    $uri = (string) $mock->getLastRequest()->getUri();
    expect($uri)->toContain('state=Consegnato');
});

it('sends pagination params', function () {
    [$client, $mock] = mockInvoicetronicClient([
        jsonResponse(200, []),
    ]);

    $client->listUpdates(page: 3, pageSize: 50);

    $uri = (string) $mock->getLastRequest()->getUri();
    expect($uri)->toContain('page=3')
        ->and($uri)->toContain('page_size=50');
});

it('handles empty result set', function () {
    [$client] = mockInvoicetronicClient([
        jsonResponse(200, []),
    ]);

    $page = $client->listUpdates();

    expect($page->isEmpty())->toBeTrue()
        ->and($page->items)->toBeEmpty();
});

it('paginates through multiple pages without hard cap', function () {
    $page1 = array_fill(0, 100, ['id' => 1, 'send_id' => 1, 'state' => 'Inviato', 'is_read' => false]);
    $page2 = array_fill(0, 50, ['id' => 2, 'send_id' => 1, 'state' => 'Consegnato', 'is_read' => false]);

    [$client] = mockInvoicetronicClient([
        jsonResponse(200, $page1),
        jsonResponse(200, $page2),
    ]);

    $firstPage = $client->listUpdates(page: 1, pageSize: 100);
    $secondPage = $client->listUpdates(page: 2, pageSize: 100);

    // First page is full (100 items), not last
    expect($firstPage->isLastPage())->toBeFalse()
        ->and($firstPage->items)->toHaveCount(100);

    // Second page is partial (50 < 100), it's the last
    expect($secondPage->isLastPage())->toBeTrue()
        ->and($secondPage->items)->toHaveCount(50);
});

it('fetches single update by id with GET /update/{id}', function () {
    [$client, $mock] = mockInvoicetronicClient([
        jsonResponse(200, ['id' => 7, 'send_id' => 42, 'state' => 'Scartato', 'is_read' => false]),
    ]);

    $update = $client->getUpdate(7);

    expect($update)->toBeInstanceOf(UpdateItem::class)
        ->and($update->id)->toBe(7)
        ->and($update->sendId)->toBe(42)
        ->and((string) $mock->getLastRequest()->getUri())->toContain('/update/7');
});
