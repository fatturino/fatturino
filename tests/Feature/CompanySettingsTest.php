<?php

use App\Settings\CompanySettings;

// Test company settings can be accessed
test('company settings can be accessed', function () {
    $settings = app(CompanySettings::class);

    expect($settings)->toBeInstanceOf(CompanySettings::class);
});

// Test company settings have required fields
test('company settings have all required fields', function () {
    $settings = app(CompanySettings::class);

    expect($settings)->toHaveProperties([
        'company_name',
        'company_vat_number',
        'company_tax_code',
        'company_address',
        'company_city',
        'company_postal_code',
        'company_province',
        'company_country',
        'company_pec',
        'company_sdi_code',
        'company_fiscal_regime',
    ]);
});

// Test company settings can be updated
test('company settings can be updated', function () {
    $settings = app(CompanySettings::class);

    $originalName = $settings->company_name;

    $settings->company_name = 'New Company Name';
    $settings->save();

    $updatedSettings = app(CompanySettings::class);
    expect($updatedSettings->company_name)->toBe('New Company Name');

    // Restore original
    $settings->company_name = $originalName;
    $settings->save();
});

// Test Italian VAT number format
test('company settings accept Italian vat number format', function () {
    $settings = app(CompanySettings::class);

    $settings->company_vat_number = 'IT12345678903';
    $settings->save();

    $updatedSettings = app(CompanySettings::class);
    expect($updatedSettings->company_vat_number)->toBe('IT12345678903');
});

// Test tax code format
test('company settings accept Italian tax code format', function () {
    $settings = app(CompanySettings::class);

    // Company tax code (11 digits)
    $settings->company_tax_code = '12345678903';
    $settings->save();

    $updatedSettings = app(CompanySettings::class);
    expect($updatedSettings->company_tax_code)->toBe('12345678903');
});

// Test address fields
test('company settings can store complete address', function () {
    $settings = app(CompanySettings::class);

    $settings->company_address = 'Via Test 123';
    $settings->company_city = 'Milano';
    $settings->company_postal_code = '20100';
    $settings->company_province = 'MI';
    $settings->company_country = 'IT';
    $settings->save();

    $updatedSettings = app(CompanySettings::class);
    expect($updatedSettings->company_address)->toBe('Via Test 123');
    expect($updatedSettings->company_city)->toBe('Milano');
    expect($updatedSettings->company_postal_code)->toBe('20100');
    expect($updatedSettings->company_province)->toBe('MI');
    expect($updatedSettings->company_country)->toBe('IT');
});

// Test electronic invoicing fields
test('company settings can store electronic invoicing data', function () {
    $settings = app(CompanySettings::class);

    $settings->company_pec = 'test@pec.it';
    $settings->company_sdi_code = '1234567';
    $settings->company_fiscal_regime = 'RF01';
    $settings->save();

    $updatedSettings = app(CompanySettings::class);
    expect($updatedSettings->company_pec)->toBe('test@pec.it');
    expect($updatedSettings->company_sdi_code)->toBe('1234567');
    expect($updatedSettings->company_fiscal_regime)->toBe('RF01');
});

// Test SDI code format (7 characters)
test('company settings accept 7-character sdi code', function () {
    $settings = app(CompanySettings::class);

    $settings->company_sdi_code = 'ABCDEFG';
    $settings->save();

    $updatedSettings = app(CompanySettings::class);
    expect($updatedSettings->company_sdi_code)->toBe('ABCDEFG');
    expect(strlen($updatedSettings->company_sdi_code))->toBe(7);
});

// Test fiscal regime codes
test('company settings accept valid fiscal regime codes', function () {
    $settings = app(CompanySettings::class);

    $validRegimes = ['RF01', 'RF02', 'RF04', 'RF05', 'RF09', 'RF10', 'RF11', 'RF18', 'RF19'];

    foreach ($validRegimes as $regime) {
        $settings->company_fiscal_regime = $regime;
        $settings->save();

        $updatedSettings = app(CompanySettings::class);
        expect($updatedSettings->company_fiscal_regime)->toBe($regime);
    }
});

// Test PEC email format
test('company settings can store pec email', function () {
    $settings = app(CompanySettings::class);

    $pecEmails = [
        'company@pec.it',
        'test.company@pec.example.com',
        'info@legalmail.it',
    ];

    foreach ($pecEmails as $pec) {
        $settings->company_pec = $pec;
        $settings->save();

        $updatedSettings = app(CompanySettings::class);
        expect($updatedSettings->company_pec)->toBe($pec);
    }
});

// Test complete company profile
test('company settings can store complete company profile', function () {
    $settings = app(CompanySettings::class);

    $settings->company_name = 'Test Company SRL';
    $settings->company_vat_number = 'IT12345678903';
    $settings->company_tax_code = '12345678903';
    $settings->company_address = 'Via Roma 1';
    $settings->company_city = 'Milano';
    $settings->company_postal_code = '20100';
    $settings->company_province = 'MI';
    $settings->company_country = 'IT';
    $settings->company_pec = 'company@pec.it';
    $settings->company_sdi_code = '1234567';
    $settings->company_fiscal_regime = 'RF01';
    $settings->save();

    $updatedSettings = app(CompanySettings::class);

    expect($updatedSettings->company_name)->toBe('Test Company SRL');
    expect($updatedSettings->company_vat_number)->toBe('IT12345678903');
    expect($updatedSettings->company_tax_code)->toBe('12345678903');
    expect($updatedSettings->company_address)->toBe('Via Roma 1');
    expect($updatedSettings->company_city)->toBe('Milano');
    expect($updatedSettings->company_postal_code)->toBe('20100');
    expect($updatedSettings->company_province)->toBe('MI');
    expect($updatedSettings->company_country)->toBe('IT');
    expect($updatedSettings->company_pec)->toBe('company@pec.it');
    expect($updatedSettings->company_sdi_code)->toBe('1234567');
    expect($updatedSettings->company_fiscal_regime)->toBe('RF01');
});

// Test settings group
test('company settings use correct group name', function () {
    expect(CompanySettings::group())->toBe('company');
});

// Test settings persistence
test('company settings persist across multiple retrievals', function () {
    $settings = app(CompanySettings::class);

    $testValue = 'Test Persistence Company '.uniqid();
    $settings->company_name = $testValue;
    $settings->save();

    // Retrieve again
    $settings1 = app(CompanySettings::class);
    expect($settings1->company_name)->toBe($testValue);

    // Retrieve once more
    $settings2 = app(CompanySettings::class);
    expect($settings2->company_name)->toBe($testValue);
});

// Test partial updates
test('company settings can be partially updated', function () {
    $settings = app(CompanySettings::class);

    $originalCity = $settings->company_city;
    $originalProvince = $settings->company_province;

    // Update only city
    $settings->company_city = 'Roma';
    $settings->save();

    $updatedSettings = app(CompanySettings::class);
    expect($updatedSettings->company_city)->toBe('Roma');
    // Province should remain unchanged
    expect($updatedSettings->company_province)->toBe($originalProvince);

    // Restore
    $settings->company_city = $originalCity;
    $settings->save();
});

// Test different Italian provinces
test('company settings accept various Italian provinces', function () {
    $settings = app(CompanySettings::class);

    $provinces = ['MI', 'RM', 'TO', 'NA', 'FI', 'BO', 'VE', 'PA', 'GE'];

    foreach ($provinces as $province) {
        $settings->company_province = $province;
        $settings->save();

        $updatedSettings = app(CompanySettings::class);
        expect($updatedSettings->company_province)->toBe($province);
    }
});

// Test postal code formats
test('company settings accept various postal code formats', function () {
    $settings = app(CompanySettings::class);

    $postalCodes = ['20100', '00100', '10121', '80100', '50121'];

    foreach ($postalCodes as $code) {
        $settings->company_postal_code = $code;
        $settings->save();

        $updatedSettings = app(CompanySettings::class);
        expect($updatedSettings->company_postal_code)->toBe($code);
    }
});
