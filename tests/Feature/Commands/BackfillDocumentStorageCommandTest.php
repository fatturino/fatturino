<?php

use App\Models\Contact;
use App\Models\FiscalDocument;
use App\Models\FiscalDocumentLine;
use App\Services\DocumentStorageService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['filesystems.disks.documents.driver' => 's3']);
    Storage::fake('local');
    Storage::fake('documents');
});

it('copies existing local snapshots to the S3 document disk', function () {
    $invoice = FiscalDocument::factory()->create([
        'xml_path' => 'documents/xml/sales/2026/legacy.xml',
        'pdf_path' => 'documents/pdf/sales/2026/legacy.pdf',
    ]);
    Storage::disk('local')->put($invoice->xml_path, '<Legacy/>');
    Storage::disk('local')->put($invoice->pdf_path, '%PDF-1.4');

    $this->artisan('documents:backfill-storage')
        ->expectsOutput('Copied: documents/xml/sales/2026/legacy.xml -> xml/sales/2026/legacy.xml')
        ->expectsOutput('Copied: documents/pdf/sales/2026/legacy.pdf -> pdf/sales/2026/legacy.pdf')
        ->assertExitCode(0);

    Storage::disk('documents')->assertExists('xml/sales/2026/legacy.xml');
    Storage::disk('documents')->assertExists('pdf/sales/2026/legacy.pdf');
    expect($invoice->refresh()->xml_path)->toBe('xml/sales/2026/legacy.xml')
        ->and($invoice->pdf_path)->toBe('pdf/sales/2026/legacy.pdf');
});

it('regenerates missing outbound XML on S3 and records its snapshot path', function () {
    $contact = Contact::factory()->create([
        'country' => 'IT',
        'sdi_code' => '0000000',
    ]);
    $invoice = FiscalDocument::factory()->create([
        'contact_id' => $contact->id,
        'xml_path' => 'documents/xml/sales/2026/missing-legacy.xml',
    ]);
    FiscalDocumentLine::query()->create([
        'fiscal_document_id' => $invoice->id,
        'description' => 'Servizio di test',
        'quantity' => '1.00',
        'unit_price' => 10000,
        'total' => 10000,
        'vat_rate' => 'R22',
    ]);

    $this->artisan('documents:backfill-storage')
        ->expectsOutputToContain('Regenerated XML:')
        ->assertExitCode(0);

    $invoice->refresh();

    expect($invoice->xml_path)->not->toBeNull()
        ->and(app(DocumentStorageService::class)->getXml($invoice->xml_path))->toContain('FatturaElettronica');
});

it('reports regenerated XML in dry run without writing it or changing the database', function () {
    $invoice = FiscalDocument::factory()->create();

    $this->artisan('documents:backfill-storage --dry-run')
        ->expectsOutputToContain('Would regenerate XML:')
        ->assertExitCode(0);

    expect($invoice->refresh()->xml_path)->toBeNull()
        ->and(Storage::disk('documents')->allFiles())->toBeEmpty();
});

it('refuses to run unless the configured document disk uses S3', function () {
    config(['filesystems.disks.documents.driver' => 'local']);

    $this->artisan('documents:backfill-storage')
        ->expectsOutput('Document storage must use the S3 driver before running this command.')
        ->assertExitCode(1);
});
