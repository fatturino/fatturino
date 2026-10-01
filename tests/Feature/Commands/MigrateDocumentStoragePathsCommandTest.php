<?php

use App\Models\FiscalDocument;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['filesystems.disks.documents.driver' => 's3']);
    Storage::fake('documents');
});

it('moves legacy S3 snapshots to canonical document paths and updates the database', function () {
    $document = FiscalDocument::factory()->create([
        'type' => 'sales',
        'date' => '2026-10-01',
        'public_id' => '01M3VVDHF70NR8S6HFCZYTPY6C',
        'xml_path' => 'xml/sales/document-01M3VVDHF70NR8S6HFCZYTPY6C/2026/IT01234567890_00001.xml',
        'pdf_path' => 'pdf/sales/document-01M3VVDHF70NR8S6HFCZYTPY6C/2026/fattura-1.pdf',
    ]);
    Storage::disk('documents')->put($document->xml_path, '<Invoice/>');
    Storage::disk('documents')->put($document->pdf_path, '%PDF-1.4');

    $this->artisan('documents:migrate-s3-paths')
        ->assertExitCode(0);

    $document->refresh();

    expect($document->xml_path)->toBe('xml/sales/2026/01M3VVDHF70NR8S6HFCZYTPY6C/IT01234567890_00001.xml')
        ->and($document->pdf_path)->toBe('pdf/sales/2026/01M3VVDHF70NR8S6HFCZYTPY6C/fattura-1.pdf');
    Storage::disk('documents')->assertExists($document->xml_path);
    Storage::disk('documents')->assertExists($document->pdf_path);
    Storage::disk('documents')->assertMissing('xml/sales/document-01M3VVDHF70NR8S6HFCZYTPY6C/2026/IT01234567890_00001.xml');
    Storage::disk('documents')->assertMissing('pdf/sales/document-01M3VVDHF70NR8S6HFCZYTPY6C/2026/fattura-1.pdf');
});

it('reports the migration in dry-run without changing S3 or the database', function () {
    $document = FiscalDocument::factory()->create([
        'type' => 'sales',
        'date' => '2026-10-01',
        'public_id' => '01M3VVDHF70NR8S6HFCZYTPY6C',
        'xml_path' => 'xml/sales/document-01M3VVDHF70NR8S6HFCZYTPY6C/2026/legacy.xml',
    ]);
    Storage::disk('documents')->put($document->xml_path, '<Invoice/>');

    $this->artisan('documents:migrate-s3-paths --dry-run')
        ->expectsOutputToContain('Would move:')
        ->assertExitCode(0);

    expect($document->refresh()->xml_path)->toBe('xml/sales/document-01M3VVDHF70NR8S6HFCZYTPY6C/2026/legacy.xml');
    Storage::disk('documents')->assertMissing('xml/sales/2026/01M3VVDHF70NR8S6HFCZYTPY6C/legacy.xml');
});

it('fails without deleting the legacy object when an existing canonical object differs', function () {
    $document = FiscalDocument::factory()->create([
        'type' => 'sales',
        'date' => '2026-10-01',
        'public_id' => '01M3VVDHF70NR8S6HFCZYTPY6C',
        'xml_path' => 'xml/sales/document-01M3VVDHF70NR8S6HFCZYTPY6C/2026/legacy.xml',
    ]);
    Storage::disk('documents')->put($document->xml_path, '<Legacy/>');
    Storage::disk('documents')->put('xml/sales/2026/01M3VVDHF70NR8S6HFCZYTPY6C/legacy.xml', '<Different/>');

    $this->artisan('documents:migrate-s3-paths')->assertExitCode(1);

    expect($document->refresh()->xml_path)->toBe('xml/sales/document-01M3VVDHF70NR8S6HFCZYTPY6C/2026/legacy.xml');
    Storage::disk('documents')->assertExists('xml/sales/document-01M3VVDHF70NR8S6HFCZYTPY6C/2026/legacy.xml');
});

it('cleans up a legacy copy after a previous database update', function () {
    $publicId = '01M3VVDHF70NR8S6HFCZYTPY6C';
    $document = FiscalDocument::factory()->create([
        'type' => 'sales',
        'date' => '2026-10-01',
        'public_id' => $publicId,
        'xml_path' => "xml/sales/2026/{$publicId}/legacy.xml",
    ]);
    Storage::disk('documents')->put($document->xml_path, '<Invoice/>');
    Storage::disk('documents')->put("xml/sales/document-{$publicId}/2026/legacy.xml", '<Invoice/>');

    $this->artisan('documents:migrate-s3-paths')->assertExitCode(0);

    Storage::disk('documents')->assertMissing("xml/sales/document-{$publicId}/2026/legacy.xml");
});
