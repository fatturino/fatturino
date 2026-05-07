<?php

use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Sequence;
use Database\Seeders\SequenceSeeder;

test('sequence can be created with a pattern', function () {
    $sequence = Sequence::create([
        'name' => 'Test Sequence',
        'type' => 'electronic_invoice',
        'pattern' => 'FE-{SEQ}',
    ]);

    expect($sequence->name)->toBe('Test Sequence');
    expect($sequence->pattern)->toBe('FE-{SEQ}');
    expect($sequence->type)->toBe('electronic_invoice');
});

test('sequence defaults to plain {SEQ} pattern', function () {
    $sequence = Sequence::create([
        'name' => 'Simple Sequence',
        'type' => 'purchase',
    ]);

    expect($sequence->pattern)->toBe('{SEQ}');
});

test('getNextNumber returns 1 for sequence with no invoices', function () {
    $sequence = Sequence::create(['name' => 'Empty', 'type' => 'quote']);

    expect($sequence->getNextNumber())->toBe(1);
});

test('getNextNumber returns correct next number', function () {
    $sequence = Sequence::create(['name' => 'Test', 'type' => 'electronic_invoice']);
    $contact = Contact::create(['name' => 'Test Client']);

    Invoice::create(['number' => 1, 'sequential_number' => 1, 'date' => now(), 'contact_id' => $contact->id, 'sequence_id' => $sequence->id]);
    expect($sequence->getNextNumber())->toBe(2);

    Invoice::create(['number' => 2, 'sequential_number' => 2, 'date' => now(), 'contact_id' => $contact->id, 'sequence_id' => $sequence->id]);
    expect($sequence->getNextNumber())->toBe(3);
});

test('getNextNumber handles gaps in invoice numbering', function () {
    $sequence = Sequence::create(['name' => 'Test', 'type' => 'electronic_invoice']);
    $contact = Contact::create(['name' => 'Test Client']);

    foreach ([1, 3, 7] as $number) {
        Invoice::create(['number' => $number, 'sequential_number' => $number, 'date' => now(), 'contact_id' => $contact->id, 'sequence_id' => $sequence->id]);
    }

    expect($sequence->getNextNumber())->toBe(8);
});

// --- getFormattedNumber with token substitution ---

test('plain {SEQ} pattern returns bare number without padding', function () {
    $sequence = Sequence::create(['name' => 'Plain', 'type' => 'electronic_invoice', 'pattern' => '{SEQ}']);

    expect($sequence->getFormattedNumber())->toBe('1');
});

test('pattern with prefix pads {SEQ} to 4 digits', function () {
    $sequence = Sequence::create(['name' => 'Prefixed', 'type' => 'electronic_invoice', 'pattern' => 'FE-{SEQ}']);

    expect($sequence->getFormattedNumber())->toBe('FE-0001');
});

test('pattern with suffix pads {SEQ} to 4 digits', function () {
    $sequence = Sequence::create(['name' => 'Suffixed', 'type' => 'electronic_invoice', 'pattern' => '{SEQ}-2026']);

    expect($sequence->getFormattedNumber())->toBe('0001-2026');
});

test('{ANNO} token is replaced with the current year', function () {
    $sequence = Sequence::create(['name' => 'Anno', 'type' => 'electronic_invoice', 'pattern' => 'FE-{SEQ}-{ANNO}']);

    expect($sequence->getFormattedNumber())->toBe('FE-0001-'.now()->year);
});

test('arbitrary pattern text is preserved', function () {
    $sequence = Sequence::create(['name' => 'Custom', 'type' => 'electronic_invoice', 'pattern' => 'Fatt{SEQ}']);

    expect($sequence->getFormattedNumber())->toBe('Fatt0001');
});

test('getFormattedNumber increments correctly', function () {
    $sequence = Sequence::create(['name' => 'Test', 'type' => 'electronic_invoice', 'pattern' => 'FAT-{SEQ}']);
    $contact = Contact::create(['name' => 'Test Client']);

    expect($sequence->getFormattedNumber())->toBe('FAT-0001');

    Invoice::create(['number' => 1, 'sequential_number' => 1, 'date' => now(), 'contact_id' => $contact->id, 'sequence_id' => $sequence->id]);
    expect($sequence->getFormattedNumber())->toBe('FAT-0002');

    for ($i = 2; $i <= 9; $i++) {
        Invoice::create(['number' => $i, 'sequential_number' => $i, 'date' => now(), 'contact_id' => $contact->id, 'sequence_id' => $sequence->id]);
    }
    expect($sequence->getFormattedNumber())->toBe('FAT-0010');
});

test('getFormattedNumber handles numbers larger than 4 digits', function () {
    $sequence = Sequence::create(['name' => 'Large', 'type' => 'electronic_invoice', 'pattern' => 'INV-{SEQ}']);
    $contact = Contact::create(['name' => 'Test Client']);

    Invoice::create(['number' => 9999, 'sequential_number' => 9999, 'date' => now(), 'contact_id' => $contact->id, 'sequence_id' => $sequence->id]);

    expect($sequence->getFormattedNumber())->toBe('INV-10000');
});

// --- Relationship ---

test('sequence has many invoices relationship', function () {
    $sequence = Sequence::create(['name' => 'Test', 'type' => 'electronic_invoice']);
    $contact = Contact::create(['name' => 'Test Client']);

    Invoice::create(['number' => 1, 'sequential_number' => 1, 'date' => now(), 'contact_id' => $contact->id, 'sequence_id' => $sequence->id]);
    Invoice::create(['number' => 2, 'sequential_number' => 2, 'date' => now(), 'contact_id' => $contact->id, 'sequence_id' => $sequence->id]);

    expect($sequence->invoices()->count())->toBe(2);
});

test('multiple sequences maintain separate numbering', function () {
    $seq1 = Sequence::create(['name' => 'Sequence 1', 'type' => 'electronic_invoice', 'pattern' => 'SEQ1-{SEQ}']);
    $seq2 = Sequence::create(['name' => 'Sequence 2', 'type' => 'purchase',           'pattern' => 'SEQ2-{SEQ}']);
    $contact = Contact::create(['name' => 'Test Client']);

    Invoice::create(['number' => 1, 'sequential_number' => 1, 'date' => now(), 'contact_id' => $contact->id, 'sequence_id' => $seq1->id]);
    Invoice::create(['number' => 2, 'sequential_number' => 2, 'date' => now(), 'contact_id' => $contact->id, 'sequence_id' => $seq1->id]);
    Invoice::create(['number' => 1, 'sequential_number' => 1, 'date' => now(), 'contact_id' => $contact->id, 'sequence_id' => $seq2->id]);

    expect($seq1->getNextNumber())->toBe(3);
    expect($seq2->getNextNumber())->toBe(2);
    expect($seq1->getFormattedNumber())->toBe('SEQ1-0003');
    expect($seq2->getFormattedNumber())->toBe('SEQ2-0002');
});

test('sequence can be deleted', function () {
    $sequence = Sequence::create(['name' => 'To Delete', 'type' => 'quote']);
    $sequence->delete();

    expect(Sequence::find($sequence->id))->toBeNull();
});

test('complete sequence lifecycle works correctly', function () {
    $sequence = Sequence::create(['name' => 'Complete', 'type' => 'electronic_invoice', 'pattern' => 'COMP-{SEQ}']);
    $contact = Contact::create(['name' => 'Test Client']);

    expect($sequence->invoices()->count())->toBe(0);
    expect($sequence->getNextNumber())->toBe(1);
    expect($sequence->getFormattedNumber())->toBe('COMP-0001');

    Invoice::create(['number' => 1, 'sequential_number' => 1, 'date' => now(), 'contact_id' => $contact->id, 'sequence_id' => $sequence->id]);

    expect($sequence->getFormattedNumber())->toBe('COMP-0002');

    for ($i = 2; $i <= 5; $i++) {
        Invoice::create(['number' => $i, 'sequential_number' => $i, 'date' => now(), 'contact_id' => $contact->id, 'sequence_id' => $sequence->id]);
    }

    expect($sequence->invoices()->count())->toBe(5);
    expect($sequence->getFormattedNumber())->toBe('COMP-0006');
});

// --- Year-based numbering ---

test('getNextNumber resets to 1 for a new year', function () {
    $sequence = Sequence::create(['name' => 'Yearly', 'type' => 'electronic_invoice', 'pattern' => '{SEQ}-{ANNO}']);
    $contact = Contact::create(['name' => 'Test Client']);

    // Create invoices in 2025
    Invoice::create(['number' => 1, 'sequential_number' => 1, 'date' => '2025-06-15', 'contact_id' => $contact->id, 'sequence_id' => $sequence->id]);
    Invoice::create(['number' => 2, 'sequential_number' => 2, 'date' => '2025-11-20', 'contact_id' => $contact->id, 'sequence_id' => $sequence->id]);

    // 2025 should continue from 3
    expect($sequence->getNextNumber(2025))->toBe(3);

    // 2026 should restart from 1 (no invoices in that year)
    expect($sequence->getNextNumber(2026))->toBe(1);

    // Create invoice in 2026
    Invoice::create(['number' => 1, 'sequential_number' => 1, 'date' => '2026-01-10', 'contact_id' => $contact->id, 'sequence_id' => $sequence->id]);

    // 2026 continues from 2, 2025 remains at 3
    expect($sequence->getNextNumber(2026))->toBe(2);
    expect($sequence->getNextNumber(2025))->toBe(3);
});

test('getFormattedNumber uses correct year', function () {
    $sequence = Sequence::create(['name' => 'AF', 'type' => 'self_invoice', 'pattern' => '{SEQ}-{ANNO}-AF']);

    expect($sequence->getFormattedNumber(2026))->toBe('0001-2026-AF');
    expect($sequence->getFormattedNumber(2025))->toBe('0001-2025-AF');
});

// --- System sequence protection ---

test('sequence can be marked as system', function () {
    $sequence = Sequence::create(['name' => 'Sistema', 'type' => 'electronic_invoice', 'is_system' => true]);

    expect($sequence->is_system)->toBeTrue();
});

test('system sequence cannot be deleted', function () {
    $sequence = Sequence::create(['name' => 'Sistema', 'type' => 'electronic_invoice', 'is_system' => true]);

    expect(fn () => $sequence->delete())
        ->toThrow(Exception::class, 'Impossibile eliminare un sezionale di sistema');
});

test('sequence in use cannot be deleted', function () {
    $sequence = Sequence::create(['name' => 'In Use', 'type' => 'purchase']);
    $contact = Contact::create(['name' => 'Test Customer']);

    Invoice::create(['number' => 1, 'sequential_number' => 1, 'date' => now(), 'contact_id' => $contact->id, 'sequence_id' => $sequence->id]);

    expect(fn () => $sequence->delete())
        ->toThrow(Exception::class, 'È utilizzato in');
});

test('unused non-system sequence can be deleted', function () {
    $sequence = Sequence::create(['name' => 'Deletable', 'type' => 'proforma', 'is_system' => false]);
    $sequence->delete();

    expect(Sequence::find($sequence->id))->toBeNull();
});

test('system sequence type cannot be changed', function () {
    $sequence = Sequence::create(['name' => 'Fatture', 'type' => 'electronic_invoice', 'is_system' => true]);

    expect(fn () => $sequence->update(['type' => 'purchase']))
        ->toThrow(Exception::class, 'Impossibile modificare il tipo');
});

test('system sequence name and pattern can be updated', function () {
    $sequence = Sequence::create([
        'name' => 'Old Name',
        'type' => 'electronic_invoice',
        'pattern' => '{SEQ}',
        'is_system' => true,
    ]);

    $sequence->update(['name' => 'New Name', 'pattern' => 'FE-{SEQ}-{ANNO}']);
    $sequence->refresh();

    expect($sequence->name)->toBe('New Name');
    expect($sequence->pattern)->toBe('FE-{SEQ}-{ANNO}');
    // Type must remain unchanged
    expect($sequence->type)->toBe('electronic_invoice');
});

// --- Seeder ---

test('seeder creates the six standard sequences', function () {
    $this->seed(SequenceSeeder::class);

    expect(Sequence::where('is_system', true)->count())->toBe(6);

    $names = Sequence::where('is_system', true)->pluck('name')->toArray();
    expect($names)->toContain(
        'Fatture Elettroniche', 'Acquisti', 'Autofatture',
        'ProForma', 'Note di Credito', 'Preventivi'
    );
});

test('seeder covers all six sequence types', function () {
    $this->seed(SequenceSeeder::class);

    foreach (['electronic_invoice', 'purchase', 'self_invoice', 'proforma', 'credit_note', 'quote'] as $type) {
        expect(Sequence::where('type', $type)->exists())->toBeTrue();
    }

    expect(Sequence::count())->toBe(6);
});
