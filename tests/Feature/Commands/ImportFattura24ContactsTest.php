<?php

use App\Models\Contact;
use Illuminate\Support\Facades\Artisan;

test('command fails when file does not exist', function () {
    $exitCode = Artisan::call('import:fattura24-contacts', [
        'file' => '/non/existent/file.csv',
    ]);

    expect($exitCode)->toBe(1); // FAILURE
});

test('command imports contacts from valid CSV', function () {
    $exitCode = Artisan::call('import:fattura24-contacts', [
        'file' => base_path('tests/fixtures/fattura24_contacts.csv'),
    ]);

    expect($exitCode)->toBe(0); // SUCCESS
    expect(Contact::count())->toBeGreaterThanOrEqual(2);
});

test('command with --update flag updates existing contacts', function () {
    Contact::create([
        'name' => 'Old Name',
        'vat_number' => '12345678903',
        'country' => 'IT',
        'is_customer' => true,
    ]);

    Artisan::call('import:fattura24-contacts', [
        'file' => base_path('tests/fixtures/fattura24_contacts.csv'),
        '--update' => true,
    ]);

    expect(Contact::where('vat_number', '12345678903')->first()->name)
        ->toBe('Azienda Test Srl');
});

test('command output contains import stats table', function () {
    Artisan::call('import:fattura24-contacts', [
        'file' => base_path('tests/fixtures/fattura24_contacts.csv'),
    ]);

    $output = Artisan::output();
    expect($output)->toContain('Import completed');
});
