<?php

use App\Models\Contact;
use App\Models\FiscalDocument;
use App\Models\Sequence;
use App\Services\Domain\DocumentNumberingService;

test('document numbering service reserves next number when no custom number is provided', function () {
    $sequence = Sequence::create([
        'name' => 'Autofatture',
        'type' => 'self_invoice',
        'pattern' => '{SEQ}-{ANNO}-AF',
    ]);

    $contact = Contact::create(['name' => 'Test Contact']);

    FiscalDocument::create([
        'number' => '27-2026-AF',
        'sequential_number' => 27,
        'date' => '2026-05-10',
        'contact_id' => $contact->id,
        'sequence_id' => $sequence->id,
        'fiscal_year' => 2026,
    ]);

    $numbering = app(DocumentNumberingService::class)->reserve($sequence, '2026-05-11');

    expect($numbering)->toBe([
        'fiscal_year' => 2026,
        'sequential_number' => 28,
        'number' => '28-2026-AF',
    ]);
});

test('document numbering service reuses sequential number from provided formatted number', function () {
    $sequence = Sequence::create([
        'name' => 'Autofatture',
        'type' => 'self_invoice',
        'pattern' => '{SEQ}-{ANNO}-AF',
    ]);

    $numbering = app(DocumentNumberingService::class)->resolve($sequence, '2026-03-01', '19-2026-AF');

    expect($numbering)->toBe([
        'fiscal_year' => 2026,
        'sequential_number' => 19,
        'number' => '19-2026-AF',
    ]);
});

test('document numbering service keeps provided number and reserves fallback sequence when pattern does not match', function () {
    $sequence = Sequence::create([
        'name' => 'Autofatture',
        'type' => 'self_invoice',
        'pattern' => '{SEQ}-{ANNO}-AF',
    ]);

    $contact = Contact::create(['name' => 'Test Contact']);

    FiscalDocument::create([
        'number' => '27-2026-AF',
        'sequential_number' => 27,
        'date' => '2026-05-10',
        'contact_id' => $contact->id,
        'sequence_id' => $sequence->id,
        'fiscal_year' => 2026,
    ]);

    $numbering = app(DocumentNumberingService::class)->resolve($sequence, '2026-05-11', 'MANUALE-XYZ');

    expect($numbering)->toBe([
        'fiscal_year' => 2026,
        'sequential_number' => 28,
        'number' => 'MANUALE-XYZ',
    ]);
});
