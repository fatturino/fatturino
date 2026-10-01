<?php

use App\Services\DocumentStorageService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['filesystems.disks.documents' => [
        'driver' => 'local',
        'root' => storage_path('framework/testing/documents'),
        'throw' => true,
    ]]);
    Storage::fake('documents');
});

it('stores immutable XML snapshots on the dedicated document disk', function () {
    $storage = app(DocumentStorageService::class);

    $path = $storage->storeXml('<Invoice/>', 'sales', 2026, 'IT01234567890_00001.xml');

    expect($path)->toBe('documents/xml/sales/2026/IT01234567890_00001.xml')
        ->and($storage->getXml($path))->toBe('<Invoice/>');

    $storage->storeXml('<Invoice/>', 'sales', 2026, 'IT01234567890_00001.xml');

    expect(fn () => $storage->storeXml('<Changed/>', 'sales', 2026, 'IT01234567890_00001.xml'))
        ->toThrow(RuntimeException::class, 'Refusing to overwrite immutable document snapshot');
});

it('stores and reads PDFs from the dedicated document disk', function () {
    $storage = app(DocumentStorageService::class);

    $path = $storage->storePdf('%PDF-1.4', 'sales', 2026, 'fattura-1.pdf');

    expect($storage->getPdf($path))->toBe('%PDF-1.4');
});

it('copies a legacy local snapshot without changing its relative path', function () {
    Storage::fake('local');
    Storage::disk('local')->put('documents/xml/sales/2026/legacy.xml', '<Legacy/>');

    $storage = app(DocumentStorageService::class);
    $storage->migrateFromLocal('documents/xml/sales/2026/legacy.xml');

    expect($storage->getXml('documents/xml/sales/2026/legacy.xml'))->toBe('<Legacy/>');
});
