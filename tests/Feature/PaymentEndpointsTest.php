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
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('total_paid', 4050)
        ->assertJsonPath('remaining_balance', 5950)
        ->assertJsonPath('payments.0.reference', 'TRN-ABC-001');

    expect(Payment::where('fiscal_document_id', $document->id)->count())->toBe(1);
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
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('total_paid', 3525)
        ->assertJsonPath('remaining_balance', 6475);

    $payment->refresh();
    expect($payment->amount)->toBe(3525);
    expect($payment->paid_at?->format('Y-m-d'))->toBe('2026-05-01');
    expect($payment->reference)->toBe('TRN-UPD-002');
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
