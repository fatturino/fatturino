<?php

use App\Services\BusinessFingerprintService;

it('builds stable business fingerprint from payload', function () {
    $service = app(BusinessFingerprintService::class);

    $payloadA = [
        'fattura_elettronica_header' => [
            'cedente_prestatore' => ['dati_anagrafici' => ['id_fiscale_iva' => ['id_codice' => 'IT01234567890']]],
            'cessionario_committente' => ['dati_anagrafici' => ['codice_fiscale' => 'RSSMRA80A01H501Z']],
        ],
        'fattura_elettronica_body' => [[
            'dati_generali' => ['dati_generali_documento' => [
                'tipo_documento' => 'td17',
                'numero' => ' abc / 12 ',
                'data' => '2026-05-01',
                'importo_totale_documento' => '122.00',
            ]],
        ]],
    ];

    $payloadB = $payloadA;
    $payloadB['fattura_elettronica_body'][0]['dati_generali']['dati_generali_documento']['tipo_documento'] = 'TD17';

    expect($service->buildFromPayload($payloadA))
        ->toBe($service->buildFromPayload($payloadB));
});
