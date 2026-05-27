<?php

use App\Models\Contact;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('uses plural table names for fiscal documents lines and payments', function () {
    expect(Schema::hasTable('fiscal_documents'))->toBeTrue();
    expect(Schema::hasTable('fiscal_documents_lines'))->toBeTrue();
    expect(Schema::hasTable('payments'))->toBeTrue();
});

it('enforces unique number by fiscal year and document type', function () {
    $contactId = DB::table('contacts')->insertGetId([
        'name' => 'ACME SRL',
        'country' => 'IT',
        'country_code' => 'IT',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('fiscal_documents')->insert([
        'public_id' => (string) str()->ulid(),
        'type' => 'sales',
        'number' => '1',
        'date' => '2026-01-10',
        'fiscal_year' => 2026,
        'contact_id' => $contactId,
        'status' => 'draft',
        'payment_status' => 'unpaid',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('fiscal_documents')->insert([
        'public_id' => (string) str()->ulid(),
        'type' => 'purchase',
        'number' => '1',
        'date' => '2026-01-11',
        'fiscal_year' => 2026,
        'contact_id' => $contactId,
        'status' => 'draft',
        'payment_status' => 'unpaid',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => DB::table('fiscal_documents')->insert([
        'public_id' => (string) str()->ulid(),
        'type' => 'sales',
        'number' => '1',
        'date' => '2026-01-12',
        'fiscal_year' => 2026,
        'contact_id' => $contactId,
        'status' => 'draft',
        'payment_status' => 'unpaid',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('stores contacts as unified contact type', function () {
    $contact = Contact::query()->create([
        'name' => 'Demo Client',
        'country' => 'IT',
        'country_code' => 'IT',
    ]);

    expect($contact->fresh()->name)->toBe('Demo Client');
});
