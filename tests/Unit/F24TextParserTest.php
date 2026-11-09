<?php

use App\Services\F24TextParser;

it('parses known IRPEF and IVA codes from plain F24-like text', function () {
    $text = <<<'TXT'
    MODELLO F24
    4001 0101 2025 1.250,00
    6031 0101 2026 320,50
    Data pagamento 16/05/2026
    Protocollo B9999900001160326/0482913
    TXT;

    $parsed = app(F24TextParser::class)->parse($text);

    expect($parsed['matched'])->toBeTrue()
        ->and($parsed['paid_at'])->toBe('2026-05-16')
        ->and($parsed['protocol_number'])->toBe('B9999900001160326/0482913')
        ->and(collect($parsed['lines'])->pluck('tribute_code')->all())->toContain('4001', '6031');
});

it('parses INPS causal codes from catalog', function () {
    $parsed = app(F24TextParser::class)->parse('Sezione INPS causale CPO periodo 01/2026 importo 800,00');

    expect($parsed['matched'])->toBeTrue()
        ->and(collect($parsed['lines'])->firstWhere('inps_causal', 'CPO'))->not->toBeNull();
});

it('returns unmatched empty lines when text has no codes', function () {
    $parsed = app(F24TextParser::class)->parse('quietanza senza campi fiscali');

    expect($parsed['matched'])->toBeFalse()
        ->and($parsed['lines'])->toBe([]);
});

it('parses Zucchetti layout July F24 with credit, multi-PXX and local tributes', function () {
    $text = file_get_contents(base_path('tests/Fixtures/f24/july.txt'));
    $parsed = app(F24TextParser::class)->parse($text);

    expect($parsed['matched'])->toBeTrue()
        ->and($parsed['paid_at'])->toBe('2026-07-20')
        ->and($parsed['lines'])->toHaveCount(6);

    $irpef = collect($parsed['lines'])->firstWhere('tribute_code', '4001');
    expect($irpef)->not->toBeNull()
        ->and($irpef['reference_year'])->toBe('2025')
        ->and($irpef['installment_or_month'])->toBe('0101')
        ->and($irpef['debit_cents'])->toBe(0)
        ->and($irpef['credit_cents'])->toBe(139500);

    $inps = collect($parsed['lines'])->where('inps_causal', 'PXX')->values();
    expect($inps)->toHaveCount(2)
        ->and($inps[0]['inps_period_from'])->toBe('01/2026')
        ->and($inps[0]['inps_period_to'])->toBe('12/2026')
        ->and($inps[0]['debit_cents'])->toBe(128275)
        ->and($inps[1]['inps_period_from'])->toBe('03/2025')
        ->and($inps[1]['inps_period_to'])->toBe('12/2025')
        ->and($inps[1]['debit_cents'])->toBe(320700);

    expect(collect($parsed['lines'])->pluck('tribute_code')->filter()->values()->all())
        ->toContain('4001', '3801', '3843', '3844');

    expect(collect($parsed['lines'])->firstWhere('tribute_code', '3801')['section'])->toBe('regioni')
        ->and(collect($parsed['lines'])->firstWhere('tribute_code', '3843')['section'])->toBe('imu')
        ->and(collect($parsed['lines'])->firstWhere('tribute_code', '3844')['section'])->toBe('imu');

    $net = collect($parsed['lines'])->sum(fn (array $l) => $l['debit_cents'] - $l['credit_cents']);
    expect($net)->toBe(342575);
});

it('parses Zucchetti layout November F24 with single INPS row', function () {
    $text = file_get_contents(base_path('tests/Fixtures/f24/nov.txt'));
    $parsed = app(F24TextParser::class)->parse($text);

    expect($parsed['matched'])->toBeTrue()
        ->and($parsed['paid_at'])->toBe('2026-11-30')
        ->and($parsed['lines'])->toHaveCount(1);

    $line = $parsed['lines'][0];
    expect($line['inps_causal'])->toBe('PXX')
        ->and($line['inps_period_from'])->toBe('01/2026')
        ->and($line['inps_period_to'])->toBe('12/2026')
        ->and($line['debit_cents'])->toBe(128275);
});

it('parses quarterly IVA F24 without rateazione and prefers estremi date over decree footer', function () {
    $text = file_get_contents(base_path('tests/Fixtures/f24/iva-q3-2026.txt'));
    $parsed = app(F24TextParser::class)->parse($text);

    expect($parsed['matched'])->toBeTrue()
        ->and($parsed['paid_at'])->toBe('2026-11-16')
        ->and($parsed['lines'])->toHaveCount(1);

    $line = $parsed['lines'][0];
    expect($line['tribute_code'])->toBe('6033')
        ->and($line['category'])->toBe('iva')
        ->and($line['reference_year'])->toBe('2026')
        ->and($line['installment_or_month'])->toBeNull()
        ->and($line['debit_cents'])->toBe(484384)
        ->and($line['credit_cents'])->toBe(0)
        ->and($line['label'])->toBe('IVA trimestrale - 3° trimestre');
});
