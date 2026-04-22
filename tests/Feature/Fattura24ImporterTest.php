<?php

use App\Models\Contact;
use App\Services\Fattura24ContactImporter;

test('import creates contacts from valid CSV', function () {
    $importer = app(Fattura24ContactImporter::class);
    $result = $importer->import(base_path('tests/fixtures/fattura24_contacts.csv'));

    // Row 1: Azienda Test Srl -> imported
    // Row 2: Fornitore Tedesco GmbH -> imported
    // Row 3: empty name -> skipped
    // Row 4: Cliente Senza IVA -> imported (no VAT is ok)
    expect($result['stats']['total'])->toBe(4);
    expect($result['stats']['imported'])->toBeGreaterThanOrEqual(2);
    expect($result['stats']['skipped'])->toBeGreaterThanOrEqual(1); // row with empty name
});

test('import skips rows with empty company name', function () {
    $importer = app(Fattura24ContactImporter::class);
    $result = $importer->import(base_path('tests/fixtures/fattura24_contacts.csv'));

    // Row 3 has empty name (Rag. Sociale) — should be skipped
    expect($result['stats']['skipped'])->toBeGreaterThanOrEqual(1);
});

test('import extracts country code from prefixed VAT number', function () {
    $importer = app(Fattura24ContactImporter::class);
    $importer->import(base_path('tests/fixtures/fattura24_contacts.csv'));

    // Row 2: P.IVA = DE987654321 -> country extracted = DE, vat_number = 987654321
    $contact = Contact::where('name', 'Fornitore Tedesco GmbH')->first();
    expect($contact)->not->toBeNull();
    expect($contact->country)->toBe('DE');
    expect($contact->vat_number)->not->toContain('DE');
});

test('import normalizes XXXXXXX sdi_code to null', function () {
    $importer = app(Fattura24ContactImporter::class);
    $importer->import(base_path('tests/fixtures/fattura24_contacts.csv'));

    // Row 2 has XXXXXXX as SDI code -> should be null
    $contact = Contact::where('name', 'Fornitore Tedesco GmbH')->first();
    expect($contact)->not->toBeNull();
    expect($contact->sdi_code)->toBeNull();
});

test('import normalizes country name ITALIA to IT', function () {
    $importer = app(Fattura24ContactImporter::class);
    $importer->import(base_path('tests/fixtures/fattura24_contacts.csv'));

    // Row 1: Paese = ITALIA -> should be stored as IT
    $contact = Contact::where('name', 'Azienda Test Srl')->first();
    expect($contact)->not->toBeNull();
    expect($contact->country)->toBe('IT');
});

test('import with updateExisting flag updates existing contacts', function () {
    // Pre-create the contact with different name
    Contact::create([
        'name' => 'Old Name',
        'vat_number' => '12345678903',
        'country' => 'IT',
        'is_customer' => true,
    ]);

    $importer = app(Fattura24ContactImporter::class);
    $result = $importer->import(base_path('tests/fixtures/fattura24_contacts.csv'), true);

    expect($result['stats']['updated'])->toBeGreaterThanOrEqual(1);
    expect(Contact::where('vat_number', '12345678903')->first()->name)->toBe('Azienda Test Srl');
});

test('import without updateExisting skips existing contacts by VAT', function () {
    Contact::create([
        'name' => 'Old Name',
        'vat_number' => '12345678903',
        'country' => 'IT',
        'is_customer' => true,
    ]);

    $importer = app(Fattura24ContactImporter::class);
    $result = $importer->import(base_path('tests/fixtures/fattura24_contacts.csv'), false);

    // Existing contact is skipped, not updated
    expect($result['stats']['skipped'])->toBeGreaterThanOrEqual(1);
    expect(Contact::where('vat_number', '12345678903')->first()->name)->toBe('Old Name');
});

test('import throws InvalidArgumentException for non-existent file', function () {
    $importer = app(Fattura24ContactImporter::class);

    expect(fn () => $importer->import('/non/existent/file.csv'))
        ->toThrow(\InvalidArgumentException::class);
});
