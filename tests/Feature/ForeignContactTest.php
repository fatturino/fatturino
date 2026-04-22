<?php

use App\Models\Contact;

beforeEach(function () {
    // Italian contact
    $this->italianContact = Contact::create([
        'name' => 'Mario Rossi SRL',
        'vat_number' => 'IT12345678903',
        'tax_code' => '12345678903',
        'country' => 'IT',
        'postal_code' => '20121',
        'province' => 'MI',
        'sdi_code' => 'ABCDEFG',
        'pec' => 'test@pec.it',
    ]);

    // EU contact (Germany)
    $this->euContact = Contact::create([
        'name' => 'Deutsche Company GmbH',
        'vat_number' => 'DE123456789',
        'country' => 'DE',
        'postal_code' => '10115',
        'address' => 'Hauptstraße 1',
        'city' => 'Berlin',
    ]);

    // Non-EU contact (USA)
    $this->nonEuContact = Contact::create([
        'name' => 'American Corp LLC',
        'vat_number' => 'US123456789',
        'country' => 'US',
        'postal_code' => '10001',
        'address' => 'Main Street 1',
        'city' => 'New York',
    ]);
});

test('Italian contact is correctly identified', function () {
    expect($this->italianContact->isItalian())->toBeTrue();
    expect($this->italianContact->isEU())->toBeTrue();
});

test('EU contact is correctly identified', function () {
    expect($this->euContact->isItalian())->toBeFalse();
    expect($this->euContact->isEU())->toBeTrue();
});

test('Non-EU contact is correctly identified', function () {
    expect($this->nonEuContact->isItalian())->toBeFalse();
    expect($this->nonEuContact->isEU())->toBeFalse();
});

test('SDI code for Italian contact uses provided value', function () {
    expect($this->italianContact->getSdiCodeForXml())->toBe('ABCDEFG');
});

test('SDI code for Italian contact without sdi_code uses default', function () {
    $contact = Contact::create([
        'name' => 'Test SRL',
        'country' => 'IT',
        'pec' => 'test@pec.it',
    ]);

    expect($contact->getSdiCodeForXml())->toBe('0000000');
});

test('SDI code for foreign contact uses XXXXXXX', function () {
    expect($this->euContact->getSdiCodeForXml())->toBe('XXXXXXX');
    expect($this->nonEuContact->getSdiCodeForXml())->toBe('XXXXXXX');
});

test('Postal code for Italian contact returns actual value', function () {
    expect($this->italianContact->getPostalCodeForXml())->toBe('20121');
});

test('Postal code for foreign contact returns 00000', function () {
    expect($this->euContact->getPostalCodeForXml())->toBe('00000');
    expect($this->nonEuContact->getPostalCodeForXml())->toBe('00000');
});

test('Province for Italian contact returns actual value', function () {
    expect($this->italianContact->getProvinceForXml())->toBe('MI');
});

test('Province for foreign contact returns EE', function () {
    expect($this->euContact->getProvinceForXml())->toBe('EE');
    expect($this->nonEuContact->getProvinceForXml())->toBe('EE');
});

test('VAT number is cleaned from country prefix', function () {
    expect($this->italianContact->getVatNumberClean())->toBe('12345678903');
    expect($this->euContact->getVatNumberClean())->toBe('123456789');
    expect($this->nonEuContact->getVatNumberClean())->toBe('123456789');
});

test('VAT number without prefix remains unchanged', function () {
    $contact = Contact::create([
        'name' => 'Test',
        'vat_number' => '12345678903',
        'country' => 'IT',
    ]);

    expect($contact->getVatNumberClean())->toBe('12345678903');
});

test('All EU countries are correctly identified', function () {
    $euCountries = [
        'AT', 'BE', 'BG', 'CY', 'HR', 'DK', 'EE', 'FI', 'FR',
        'DE', 'GR', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
        'PL', 'PT', 'CZ', 'RO', 'SK', 'SI', 'ES', 'SE', 'HU',
    ];

    foreach ($euCountries as $countryCode) {
        $contact = Contact::create([
            'name' => 'Test Company',
            'country' => $countryCode,
        ]);

        expect($contact->isEU())
            ->toBeTrue("Country {$countryCode} should be identified as EU");
    }
});

test('Non-EU countries are correctly identified', function () {
    $nonEuCountries = ['US', 'CH', 'GB', 'CN', 'JP', 'CA', 'AU'];

    foreach ($nonEuCountries as $countryCode) {
        $contact = Contact::create([
            'name' => 'Test Company',
            'country' => $countryCode,
        ]);

        expect($contact->isEU())
            ->toBeFalse("Country {$countryCode} should not be identified as EU");
    }
});
