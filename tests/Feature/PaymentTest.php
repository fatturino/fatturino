<?php

use App\Enums\PaymentStatus;
use App\Enums\VatRate;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Payment;

/**
 * Helper: create an invoice with a line so it has a meaningful total_gross.
 */
function createInvoiceWithLine(int $totalGross = 10000): Invoice
{
    $contact = Contact::create(['name' => 'Test Client']);

    $invoice = Invoice::create([
        'number' => uniqid('INV-'),
        'date' => now(),
        'contact_id' => $contact->id,
    ]);

    $invoice->lines()->create([
        'description' => 'Test line',
        'quantity' => 1,
        'unit_price' => $totalGross,
        'vat_rate' => VatRate::N4->value, // 0% exempt rate
        'total' => $totalGross,
    ]);

    $invoice->refresh();

    return $invoice;
}

test('recording a payment updates total_paid and sets status to paid', function () {
    $invoice = createInvoiceWithLine(10000);

    Payment::create([
        'invoice_id' => $invoice->id,
        'amount' => 10000,
        'paid_at' => now()->format('Y-m-d'),
    ]);

    $invoice->refresh();
    $invoice->recalculatePaymentStatus();
    $invoice->refresh();

    expect($invoice->total_paid)->toBe(10000);
    expect($invoice->payment_status)->toBe(PaymentStatus::Paid);
});

test('a partial payment sets status to partial with remaining balance', function () {
    $invoice = createInvoiceWithLine(10000);

    Payment::create([
        'invoice_id' => $invoice->id,
        'amount' => 4000,
        'paid_at' => now()->format('Y-m-d'),
    ]);

    $invoice->refresh();
    $invoice->recalculatePaymentStatus();
    $invoice->refresh();

    expect($invoice->total_paid)->toBe(4000);
    expect($invoice->payment_status)->toBe(PaymentStatus::Partial);
    expect($invoice->remainingBalance())->toBe(6000);
});

test('multiple partial payments correctly sum to remaining balance', function () {
    $invoice = createInvoiceWithLine(10000);

    Payment::create(['invoice_id' => $invoice->id, 'amount' => 3000, 'paid_at' => now()]);
    Payment::create(['invoice_id' => $invoice->id, 'amount' => 3000, 'paid_at' => now()]);

    $invoice->refresh();
    $invoice->recalculatePaymentStatus();
    $invoice->refresh();

    expect($invoice->total_paid)->toBe(6000);
    expect($invoice->remainingBalance())->toBe(4000);
    expect($invoice->payment_status)->toBe(PaymentStatus::Partial);
});

test('payments totalling the full amount set status to paid', function () {
    $invoice = createInvoiceWithLine(10000);

    Payment::create(['invoice_id' => $invoice->id, 'amount' => 5000, 'paid_at' => now()]);
    Payment::create(['invoice_id' => $invoice->id, 'amount' => 5000, 'paid_at' => now()]);

    $invoice->refresh();
    $invoice->recalculatePaymentStatus();
    $invoice->refresh();

    expect($invoice->total_paid)->toBe(10000);
    expect($invoice->payment_status)->toBe(PaymentStatus::Paid);
    expect($invoice->remainingBalance())->toBe(0);
});

test('deleting a payment recalculates status back to unpaid', function () {
    $invoice = createInvoiceWithLine(10000);

    $payment = Payment::create([
        'invoice_id' => $invoice->id,
        'amount' => 10000,
        'paid_at' => now()->format('Y-m-d'),
    ]);

    $invoice->refresh();
    $invoice->recalculatePaymentStatus();

    $payment->delete();
    $invoice->refresh();
    $invoice->recalculatePaymentStatus();
    $invoice->refresh();

    expect($invoice->total_paid)->toBe(0);
    expect($invoice->payment_status)->toBe(PaymentStatus::Unpaid);
});

test('unpaid invoice with past due date is set to overdue', function () {
    $contact = Contact::create(['name' => 'Test Client']);

    $invoice = Invoice::create([
        'number' => uniqid('INV-'),
        'date' => now()->subMonths(2),
        'contact_id' => $contact->id,
        'due_date' => now()->subMonth()->format('Y-m-d'),
        'total_gross' => 10000,
    ]);

    $invoice->recalculatePaymentStatus();
    $invoice->refresh();

    expect($invoice->payment_status)->toBe(PaymentStatus::Overdue);
});

test('remaining balance never goes below zero', function () {
    $invoice = createInvoiceWithLine(5000);

    // Record more than the total
    Payment::create(['invoice_id' => $invoice->id, 'amount' => 6000, 'paid_at' => now()]);

    $invoice->refresh();
    $invoice->recalculatePaymentStatus();
    $invoice->refresh();

    expect($invoice->remainingBalance())->toBe(0);
    expect($invoice->payment_status)->toBe(PaymentStatus::Paid);
});

test('payment is cascade deleted when the invoice is deleted', function () {
    $invoice = createInvoiceWithLine(10000);

    Payment::create([
        'invoice_id' => $invoice->id,
        'amount' => 10000,
        'paid_at' => now()->format('Y-m-d'),
    ]);

    expect(Payment::where('invoice_id', $invoice->id)->count())->toBe(1);

    // Delete the invoice (withoutGlobalScopes so the query finds it)
    Invoice::withoutGlobalScopes()->where('id', $invoice->id)->delete();

    expect(Payment::where('invoice_id', $invoice->id)->count())->toBe(0);
});
