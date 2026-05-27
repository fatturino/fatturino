<?php

use App\Models\Contact;
use App\Models\Sequence;
use App\Models\User;

it('rejects sales invoice api create without sanctum authentication', function () {
    $contact = Contact::factory()->create();
    $sequence = Sequence::factory()->create();

    $response = $this->postJson('/api/sales-invoices', salesInvoicePayload($contact->id, $sequence->id));

    $response->assertUnauthorized();
});

it('supports sales invoice api create with sanctum bearer token', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create();
    $sequence = Sequence::factory()->create();
    $token = $user->createToken('sales-invoice-api-test-token')->plainTextToken;

    $response = $this
        ->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/sales-invoices', salesInvoicePayload($contact->id, $sequence->id));

    $response->assertOk();
    $response->assertJsonPath('message', 'Fattura creata.');
    expect($response->json('invoice_id'))->not->toBeNull();
});

function salesInvoicePayload(int $contactId, int $sequenceId): array
{
    return [
        'contact_id' => $contactId,
        'sequence_id' => $sequenceId,
        'date' => '2026-05-21',
        'document_type' => 'TD01',
        'vat_payability' => 'I',
        'lines' => [
            [
                'description' => 'Linea API Sanctum',
                'quantity' => 1,
                'unit_price' => 100,
                'vat_rate' => 'R22',
            ],
        ],
    ];
}
