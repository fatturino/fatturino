<?php

use App\Enums\VatRate;
use App\Models\Payment;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\SelfInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

dataset('paymentDocuments', [
    'sales' => [SalesInvoice::class, '/sell-invoices'],
    'purchase' => [PurchaseInvoice::class, '/purchase-invoices'],
    'self' => [SelfInvoice::class, '/self-invoices'],
]);

function createDocumentWithTotal(string $modelClass, int $total = 10000)
{
    $document = $modelClass::factory()->create();

    $document->lines()->create([
        'description' => 'Test line',
        'quantity' => 1,
        'unit_price' => $total,
        'vat_rate' => VatRate::N4->value,
        'total' => $total,
    ]);
    $document->calculateTotals();
    $document->refresh();

    return $document;
}

test('record payment endpoint returns updated aggregates and payments list', function (string $modelClass, string $basePath) {
    $user = User::factory()->create();
    $document = createDocumentWithTotal($modelClass, 10000);

    $response = $this->actingAs($user)->postJson("{$basePath}/{$document->id}/payments", [
        'amount' => 40.50,
        'paid_at' => null,
        'reference' => 'TRN-ABC-001',
        'notes' => 'Saldo fattura aprile',
        'bank_name' => 'Banca Uno',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('total_paid', 4050)
        ->assertJsonPath('remaining_balance', 5950)
        ->assertJsonPath('payments.0.reference', 'TRN-ABC-001')
        ->assertJsonPath('payments.0.notes', 'Saldo fattura aprile')
        ->assertJsonPath('payments.0.bank_name', 'Banca Uno');

    $payment = Payment::where('fiscal_document_id', $document->id)->sole();

    expect($payment->reference)->toBe('TRN-ABC-001');
    expect($payment->notes)->toBe('Saldo fattura aprile');
    expect($payment->bank_name)->toBe('Banca Uno');
})->with('paymentDocuments');

test('update payment endpoint updates amount and date', function (string $modelClass, string $basePath) {
    $user = User::factory()->create();
    $document = createDocumentWithTotal($modelClass, 10000);

    $payment = $document->payments()->create([
        'amount' => 2000,
        'paid_at' => null,
    ]);
    $document->recalculatePaymentStatus();

    $response = $this->actingAs($user)->putJson("{$basePath}/{$document->id}/payments/{$payment->id}", [
        'amount' => 35.25,
        'paid_at' => '2026-05-01',
        'reference' => 'TRN-UPD-002',
        'notes' => 'Saldo con secondo conto',
        'bank_name' => 'Banca Due',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('total_paid', 3525)
        ->assertJsonPath('remaining_balance', 6475)
        ->assertJsonPath('payments.0.paid_at', '2026-05-01')
        ->assertJsonPath('payments.0.reference', 'TRN-UPD-002')
        ->assertJsonPath('payments.0.notes', 'Saldo con secondo conto')
        ->assertJsonPath('payments.0.bank_name', 'Banca Due');

    $payment->refresh();
    expect($payment->amount)->toBe(3525);
    expect($payment->paid_at?->format('Y-m-d'))->toBe('2026-05-01');
    expect($payment->reference)->toBe('TRN-UPD-002');
    expect($payment->notes)->toBe('Saldo con secondo conto');
    expect($payment->bank_name)->toBe('Banca Due');
})->with('paymentDocuments');

test('delete payment endpoint removes payment and recalculates totals', function (string $modelClass, string $basePath) {
    $user = User::factory()->create();
    $document = createDocumentWithTotal($modelClass, 10000);

    $payment = $document->payments()->create([
        'amount' => 2000,
        'paid_at' => '2026-05-02',
    ]);
    $document->recalculatePaymentStatus();

    $response = $this->actingAs($user)->deleteJson("{$basePath}/{$document->id}/payments/{$payment->id}");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('total_paid', 0)
        ->assertJsonPath('remaining_balance', 10000);

    expect(Payment::query()->whereKey($payment->id)->exists())->toBeFalse();
})->with('paymentDocuments');

test('concurrent payment mutations leave persisted totals synchronized with payment records', function (string $modelClass, string $basePath) {
    $user = User::factory()->create();
    $document = createDocumentWithTotal($modelClass, 10000);

    $firstResponse = $this->actingAs($user)->postJson("{$basePath}/{$document->id}/payments", [
        'amount' => 10.01,
    ]);
    $secondResponse = $this->actingAs($user)->postJson("{$basePath}/{$document->id}/payments", [
        'amount' => 20.02,
    ]);

    $firstResponse->assertOk()->assertJsonPath('total_paid', 1001);
    $secondResponse->assertOk()->assertJsonPath('total_paid', 3003);

    $payments = $document->payments()->orderBy('id')->get();
    $secondPayment = $payments->last();

    $this->actingAs($user)->putJson("{$basePath}/{$document->id}/payments/{$secondPayment->id}", [
        'amount' => 35.25,
    ])->assertOk()->assertJsonPath('total_paid', 4526);

    $document->refresh();

    expect($document->total_paid)->toBe(4526)
        ->and((int) $document->payments()->sum('amount'))->toBe(4526)
        ->and($document->paymentStatusValue())->toBe('partial');
})->with('paymentDocuments');

test('payment mutations cannot target a payment belonging to another routed document', function (string $modelClass, string $basePath) {
    $user = User::factory()->create();
    $routedDocument = createDocumentWithTotal($modelClass, 10000);
    $otherDocument = createDocumentWithTotal($modelClass, 10000);
    $payment = $otherDocument->payments()->create(['amount' => 1000]);
    $otherDocument->recalculatePaymentStatus();

    $this->actingAs($user)->putJson("{$basePath}/{$routedDocument->id}/payments/{$payment->id}", [
        'amount' => 50,
    ])->assertNotFound();

    $this->actingAs($user)->deleteJson("{$basePath}/{$routedDocument->id}/payments/{$payment->id}")
        ->assertNotFound();

    $routedDocument->refresh();
    $otherDocument->refresh();
    $payment->refresh();

    expect($payment->fiscal_document_id)->toBe($otherDocument->id)
        ->and($payment->amount)->toBe(1000)
        ->and($routedDocument->total_paid)->toBe(0)
        ->and($otherDocument->total_paid)->toBe(1000);
})->with('paymentDocuments');
