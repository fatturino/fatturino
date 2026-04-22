<?php

use App\Models\Contact;
use App\Models\Invoice;

test('invoice fiscal_year is auto-set from the date field on creation', function () {
    $contact = Contact::factory()->create();

    $invoice = Invoice::create([
        'number' => 'FT-001',
        'date' => '2023-07-15',
        'contact_id' => $contact->id,
    ]);

    expect($invoice->fiscal_year)->toBe(2023);
});

test('invoices are filtered by fiscal_year in queries', function () {
    $contact = Contact::factory()->create();

    Invoice::create(['number' => 'FT-2023', 'date' => '2023-01-10', 'contact_id' => $contact->id]);
    Invoice::create(['number' => 'FT-2024', 'date' => '2024-05-20', 'contact_id' => $contact->id]);
    Invoice::create(['number' => 'FT-2025', 'date' => '2025-03-01', 'contact_id' => $contact->id]);

    $invoices2024 = Invoice::where('fiscal_year', 2024)->get();
    expect($invoices2024)->toHaveCount(1);
    expect($invoices2024->first()->number)->toBe('FT-2024');
});

test('invoice from past year reports correct fiscal_year', function () {
    $contact = Contact::factory()->create();

    $invoice = Invoice::create([
        'number' => 'FT-OLD',
        'date' => now()->subYears(2)->format('Y-06-15'),
        'contact_id' => $contact->id,
    ]);

    expect($invoice->fiscal_year)->toBe(now()->year - 2);
});

test('fiscal_year is not overwritten if already set on creation', function () {
    $contact = Contact::factory()->create();

    // Explicitly set fiscal_year different from date year
    $invoice = Invoice::create([
        'number' => 'FT-OVERRIDE',
        'date' => '2024-01-01',
        'contact_id' => $contact->id,
        'fiscal_year' => 2023, // Explicitly set to previous year
    ]);

    // The booted hook only sets fiscal_year if not already set
    expect($invoice->fiscal_year)->toBe(2023);
});
