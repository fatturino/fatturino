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

    $path = $storage->storeXml('<Invoice/>', 'sales', 2026, '01M3VVDHF70NR8S6HFCZYTPY6C', 'IT01234567890_00001.xml');

    expect($path)->toBe('documents/xml/sales/2026/01M3VVDHF70NR8S6HFCZYTPY6C/IT01234567890_00001.xml')
        ->and($storage->getXml($path))->toBe('<Invoice/>');

    $storage->storeXml('<Invoice/>', 'sales', 2026, '01M3VVDHF70NR8S6HFCZYTPY6C', 'IT01234567890_00001.xml');

    expect(fn () => $storage->storeXml('<Changed/>', 'sales', 2026, '01M3VVDHF70NR8S6HFCZYTPY6C', 'IT01234567890_00001.xml'))
        ->toThrow(RuntimeException::class, 'Refusing to overwrite immutable document snapshot');
});

it('stores and reads PDFs from the dedicated document disk', function () {
    $storage = app(DocumentStorageService::class);

    $path = $storage->storePdf('%PDF-1.4', 'sales', 2026, '01M3VVDHF70NR8S6HFCZYTPY6C', 'fattura-1.pdf');

    expect($path)->toBe('documents/pdf/sales/2026/01M3VVDHF70NR8S6HFCZYTPY6C/fattura-1.pdf')
        ->and($storage->getPdf($path))->toBe('%PDF-1.4');
});

it('uses the document disk root as the S3 namespace root', function () {
    config(['filesystems.disks.documents.driver' => 's3']);
    Storage::fake('documents');

    $path = app(DocumentStorageService::class)->storeXml(
        '<Invoice/>',
        'sales',
        2026,
        '01M3VVDHF70NR8S6HFCZYTPY6C',
        'IT01234567890_00001.xml',
    );

    expect($path)->toBe('xml/sales/2026/01M3VVDHF70NR8S6HFCZYTPY6C/IT01234567890_00001.xml')
        ->and(Storage::disk('documents')->exists($path))->toBeTrue();
});
