<?php

use App\Models\Contact;
use App\Models\FiscalDocument;
use App\Models\PurchaseInvoice;
use App\Models\SelfInvoice;

// Test basic contact creation
test('contact can be created with required fields', function () {
    $contact = Contact::create([
        'name' => 'Test Client',
        'vat_number' => 'IT12345678903',
        'country' => 'IT',
    ]);

    expect($contact->name)->toBe('Test Client');
    expect($contact->vat_number)->toBe('IT12345678903');
    expect($contact->country)->toBe('IT');
});

// Test Italian contact detection
test('isItalian returns true for Italian contact using country field', function () {
    $contact = Contact::create([
        'name' => 'Italian Client',
        'country' => 'IT',
    ]);

    expect($contact->isItalian())->toBeTrue();
});

test('isItalian returns true for Italian contact using country_code fallback', function () {
    $contact = Contact::create([
        'name' => 'Italian Client',
        'country_code' => 'IT',
    ]);

    expect($contact->isItalian())->toBeTrue();
});

test('isItalian returns false for non-Italian contact', function () {
    $contact = Contact::create([
        'name' => 'Foreign Client',
        'country' => 'DE',
    ]);

    expect($contact->isItalian())->toBeFalse();
});

test('isItalian prioritizes country field over country_code', function () {
    $contact = Contact::create([
        'name' => 'Client',
        'country' => 'DE',
        'country_code' => 'IT',
    ]);

    expect($contact->isItalian())->toBeFalse();
});

// Test EU contact detection
test('isEU returns true for EU countries', function () {
    $euCountries = ['AT', 'BE', 'BG', 'FR', 'DE', 'ES', 'IT'];

    foreach ($euCountries as $countryCode) {
        $contact = Contact::create([
            'name' => 'EU Client',
            'country' => $countryCode,
        ]);

        expect($contact->isEU())->toBeTrue("Country {$countryCode} should be EU");
    }
});

test('isEU returns false for non-EU countries', function () {
    $nonEuCountries = ['US', 'GB', 'CH', 'CN', 'JP'];

    foreach ($nonEuCountries as $countryCode) {
        $contact = Contact::create([
            'name' => 'Non-EU Client',
            'country' => $countryCode,
        ]);

        expect($contact->isEU())->toBeFalse("Country {$countryCode} should not be EU");
    }
});

// Test SDI code for XML
test('getSdiCodeForXml returns provided code for Italian contact', function () {
    $contact = Contact::create([
        'name' => 'Italian Client',
        'country' => 'IT',
        'sdi_code' => '1234567',
    ]);

    expect($contact->getSdiCodeForXml())->toBe('1234567');
});

test('getSdiCodeForXml returns default 0000000 for Italian contact without sdi_code', function () {
    $contact = Contact::create([
        'name' => 'Italian Client',
        'country' => 'IT',
    ]);

    expect($contact->getSdiCodeForXml())->toBe('0000000');
});

test('getSdiCodeForXml returns XXXXXXX for foreign contact', function () {
    $contact = Contact::create([
        'name' => 'Foreign Client',
        'country' => 'DE',
    ]);

    expect($contact->getSdiCodeForXml())->toBe('XXXXXXX');
});

// Test postal code for XML
test('getPostalCodeForXml returns postal code for Italian contact', function () {
    $contact = Contact::create([
        'name' => 'Italian Client',
        'country' => 'IT',
        'postal_code' => '20100',
    ]);

    expect($contact->getPostalCodeForXml())->toBe('20100');
});

test('getPostalCodeForXml returns empty string for Italian contact without postal code', function () {
    $contact = Contact::create([
        'name' => 'Italian Client',
        'country' => 'IT',
    ]);

    expect($contact->getPostalCodeForXml())->toBe('');
});

test('getPostalCodeForXml returns 00000 for foreign contact', function () {
    $contact = Contact::create([
        'name' => 'Foreign Client',
        'country' => 'DE',
        'postal_code' => '10115',
    ]);

    expect($contact->getPostalCodeForXml())->toBe('00000');
});

// Test province for XML
test('getProvinceForXml returns province for Italian contact', function () {
    $contact = Contact::create([
        'name' => 'Italian Client',
        'country' => 'IT',
        'province' => 'MI',
    ]);

    expect($contact->getProvinceForXml())->toBe('MI');
});

test('getProvinceForXml returns empty string for Italian contact without province', function () {
    $contact = Contact::create([
        'name' => 'Italian Client',
        'country' => 'IT',
    ]);

    expect($contact->getProvinceForXml())->toBe('');
});

test('getProvinceForXml returns EE for foreign contact', function () {
    $contact = Contact::create([
        'name' => 'Foreign Client',
        'country' => 'DE',
        'province' => 'Berlin',
    ]);

    expect($contact->getProvinceForXml())->toBe('EE');
});

// Test VAT number cleaning
test('getVatNumberClean removes country prefix from VAT number', function () {
    $testCases = [
        'IT12345678903' => '12345678903',
        'DE123456789' => '123456789',
        'FR12345678903' => '12345678903',
        'ES12345678' => '12345678',
    ];

    foreach ($testCases as $input => $expected) {
        $contact = Contact::create([
            'name' => 'Client',
            'vat_number' => $input,
        ]);

        expect($contact->getVatNumberClean())->toBe($expected);
    }
});

test('getVatNumberClean returns empty string when vat_number is null', function () {
    $contact = Contact::create([
        'name' => 'Client',
    ]);

    expect($contact->getVatNumberClean())->toBe('');
});

test('getVatNumberClean returns original if no country prefix found', function () {
    $contact = Contact::create([
        'name' => 'Client',
        'vat_number' => '12345678903',
    ]);

    expect($contact->getVatNumberClean())->toBe('12345678903');
});

// Test complete contact scenarios
test('complete Italian contact has all required SDI data', function () {
    $contact = Contact::create([
        'name' => 'Italian Company SRL',
        'vat_number' => 'IT12345678903',
        'tax_code' => 'RSSMRA80A01H501U',
        'address' => 'Via Roma 1',
        'city' => 'Milano',
        'postal_code' => '20100',
        'province' => 'MI',
        'country' => 'IT',
        'sdi_code' => '1234567',
        'pec' => 'test@pec.it',
    ]);

    expect($contact->isItalian())->toBeTrue();
    expect($contact->getSdiCodeForXml())->toBe('1234567');
    expect($contact->getPostalCodeForXml())->toBe('20100');
    expect($contact->getProvinceForXml())->toBe('MI');
    expect($contact->getVatNumberClean())->toBe('12345678903');
});

test('complete foreign contact has correct SDI formatting', function () {
    $contact = Contact::create([
        'name' => 'Foreign Company GmbH',
        'vat_number' => 'DE123456789',
        'address' => 'Strasse 1',
        'city' => 'Berlin',
        'postal_code' => '10115',
        'province' => 'Berlin',
        'country' => 'DE',
    ]);

    expect($contact->isItalian())->toBeFalse();
    expect($contact->isEU())->toBeTrue();
    expect($contact->getSdiCodeForXml())->toBe('XXXXXXX');
    expect($contact->getPostalCodeForXml())->toBe('00000');
    expect($contact->getProvinceForXml())->toBe('EE');
    expect($contact->getVatNumberClean())->toBe('123456789');
});

test('is_customer is true when contact has at least one sales invoice', function () {
    $contact = Contact::factory()->create();

    expect($contact->is_customer)->toBeFalse();

    FiscalDocument::factory()->create([
        'contact_id' => $contact->id,
        'type' => 'sales',
    ]);

    expect($contact->fresh()->is_customer)->toBeTrue();
});

test('is_supplier is true when contact has at least one purchase invoice', function () {
    $contact = Contact::factory()->create();

    expect($contact->is_supplier)->toBeFalse();

    PurchaseInvoice::factory()->create([
        'contact_id' => $contact->id,
    ]);

    expect($contact->fresh()->is_supplier)->toBeTrue();
});

test('is_supplier is true when contact has at least one self invoice', function () {
    $contact = Contact::factory()->create();

    expect($contact->is_supplier)->toBeFalse();

    SelfInvoice::factory()->create([
        'contact_id' => $contact->id,
    ]);

    expect($contact->fresh()->is_supplier)->toBeTrue();
});

test('contact can be both client and supplier', function () {
    $contact = Contact::factory()->create();

    FiscalDocument::factory()->create([
        'contact_id' => $contact->id,
        'type' => 'sales',
    ]);
    PurchaseInvoice::factory()->create([
        'contact_id' => $contact->id,
    ]);

    $reloaded = $contact->fresh();
    expect($reloaded->is_customer)->toBeTrue();
    expect($reloaded->is_supplier)->toBeTrue();
});

test('clients and suppliers scopes are based on fiscal documents', function () {
    $onlyClient = Contact::factory()->create();
    $onlySupplier = Contact::factory()->create();
    $both = Contact::factory()->create();
    $none = Contact::factory()->create();

    FiscalDocument::factory()->create(['contact_id' => $onlyClient->id, 'type' => 'sales']);
    PurchaseInvoice::factory()->create(['contact_id' => $onlySupplier->id]);
    FiscalDocument::factory()->create(['contact_id' => $both->id, 'type' => 'sales']);
    SelfInvoice::factory()->create(['contact_id' => $both->id]);

    expect(Contact::query()->clients()->pluck('id')->all())
        ->toContain($onlyClient->id, $both->id)
        ->not->toContain($onlySupplier->id, $none->id);

    expect(Contact::query()->suppliers()->pluck('id')->all())
        ->toContain($onlySupplier->id, $both->id)
        ->not->toContain($onlyClient->id, $none->id);
});
