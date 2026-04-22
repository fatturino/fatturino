<?php

return [
    'name' => env('COMPANY_NAME', 'My Company S.r.l.'),
    'vat_number' => env('COMPANY_VAT_NUMBER', 'IT12345678903'),
    'tax_code' => env('COMPANY_TAX_CODE', '12345678903'),
    'address' => env('COMPANY_ADDRESS', 'Via Roma 1'),
    'city' => env('COMPANY_CITY', 'Roma'),
    'postal_code' => env('COMPANY_POSTAL_CODE', '00100'),
    'province' => env('COMPANY_PROVINCE', 'RM'),
    'country' => env('COMPANY_COUNTRY', 'IT'),
    'pec' => env('COMPANY_PEC', 'mycompany@pec.it'),
    'sdi_code' => env('COMPANY_SDI_CODE', '0000000'),
    'fiscal_regime' => env('COMPANY_FISCAL_REGIME', 'RF01'), // RF01 = Ordinario
];
