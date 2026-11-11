<?php

use App\Sdi\OpenApiRejectionPresenter;

test('presenter maps known OpenAPI rejection families to Italian operational messages', function () {
    $presenter = new OpenApiRejectionPresenter;

    expect($presenter->present(400, ['message' => 'validation failed', 'error' => 230]))
        ->toMatchArray([
            'error_code' => '230',
            'message' => 'Il documento non supera i controlli del provider. Controlla i dati obbligatori e correggi il documento.',
        ])
        ->and($presenter->present(402, ['message' => 'billing unavailable', 'error' => 611]))
        ->toMatchArray([
            'error_code' => '611',
            'message' => 'Credito insufficiente sul servizio SDI. Verifica il piano o il wallet del provider, poi riprova.',
        ])
        ->and($presenter->present(422, ['message' => 'malformed request', 'error' => '802']))
        ->toMatchArray([
            'error_code' => '802',
            'message' => 'XML non valido o malformato. Rigenera il documento e riprova l’invio.',
        ]);
});

test('presenter appends only safe validation details and drops unsafe remote content', function () {
    $presenter = new OpenApiRejectionPresenter;

    $safe = $presenter->present(400, [
        'message' => 'An error occurred: fattura_elettronica_header.dati_trasmissione.codice_destinatario: This value should not be blank.',
        'error' => 230,
    ]);

    expect($safe['message'])->toContain('Dettaglio: fattura_elettronica_header.dati_trasmissione.codice_destinatario: This value should not be blank.')
        ->and($safe['error_code'])->toBe('230');

    $unsafe = $presenter->present(400, [
        'message' => 'Billing error message: Insufficient Credit in Wallet: 200 > 1 code 810',
        'error' => 230,
    ]);

    expect($unsafe['message'])->toBe('Il documento non supera i controlli del provider. Controlla i dati obbligatori e correggi il documento.')
        ->and($unsafe['message'])->not->toContain('Wallet')
        ->and($unsafe['message'])->not->toContain('Credit');

    $sensitive = $presenter->present(422, [
        'message' => 'SENSITIVE_REMOTE_MESSAGE_DO_NOT_LEAK',
        'error' => 'SENSITIVE_REMOTE_CODE_DO_NOT_LEAK',
    ]);

    expect($sensitive['error_code'])->toBeNull()
        ->and($sensitive['message'])->toBe('Il provider ha rifiutato il documento prima dell’accettazione.')
        ->and(json_encode($sensitive))->not->toContain('SENSITIVE_REMOTE_MESSAGE_DO_NOT_LEAK')
        ->not->toContain('SENSITIVE_REMOTE_CODE_DO_NOT_LEAK');
});
