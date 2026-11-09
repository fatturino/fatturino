<?php

use App\Support\F24TributeCode;

it('loads curated F24 tribute codes from json', function () {
    F24TributeCode::clearCache();

    expect(F24TributeCode::all())->not->toBeEmpty()
        ->and(F24TributeCode::find('4001')['label'] ?? null)->toContain('IRPEF')
        ->and(F24TributeCode::label('1792'))->toContain('1792');
});

it('filters substitute tax codes for non-forfettario regimes', function () {
    $options = F24TributeCode::options(regime: 'RF01');

    expect(collect($options)->pluck('id'))->not->toContain('1792')
        ->and(collect($options)->pluck('id'))->toContain('4001');
});

it('filters iva and irpef for RF19 regime', function () {
    $options = F24TributeCode::options(regime: 'RF19');

    expect(collect($options)->pluck('id'))->toContain('1792')
        ->and(collect($options)->pluck('id'))->not->toContain('4001')
        ->and(collect($options)->pluck('id'))->not->toContain('6001');
});
