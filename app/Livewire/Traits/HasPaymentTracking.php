<?php

namespace App\Livewire\Traits;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Provides payment recording functionality to Livewire Edit components.
 *
 * The host component must implement:
 *   - getPayableInvoice(): Model  — returns the invoice model being edited
 */
trait HasPaymentTracking
{
    public bool $paymentModal = false;

    public string $newPaymentAmount = '';

    public string $newPaymentDate = '';

    public ?string $newPaymentMethod = null;

    public ?string $newPaymentReference = null;

    public ?string $newPaymentNotes = null;

    /**
     * Open the payment modal, pre-filling the amount with the remaining balance.
     */
    public function openPaymentModal(): void
    {
        $invoice = $this->getPayableInvoice();

        $remaining = $invoice->remainingBalance();
        $this->newPaymentAmount = $remaining > 0
            ? number_format($remaining / 100, 2, '.', '')
            : '';

        $this->newPaymentDate = now()->format('Y-m-d');
        $this->newPaymentMethod = $invoice->payment_method?->value;
        $this->newPaymentReference = null;
        $this->newPaymentNotes = null;

        $this->paymentModal = true;
    }

    /**
     * Validate and record a new payment, then recompute the invoice payment status.
     */
    public function addPayment(): void
    {
        $this->validate([
            'newPaymentAmount' => 'required|numeric|min:0.01',
            'newPaymentDate' => 'nullable|date',
        ]);

        $invoice = $this->getPayableInvoice();
        $amountCents = (int) round((float) $this->newPaymentAmount * 100);

        Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => $amountCents,
            'paid_at' => $this->newPaymentDate ?: null,
            'payment_method' => $this->newPaymentMethod ?: null,
            'reference' => $this->newPaymentReference ?: null,
            'notes' => $this->newPaymentNotes ?: null,
        ]);

        // Refresh model to get updated total_gross before recalculating
        $invoice->refresh();
        $invoice->recalculatePaymentStatus();

        // Reset form fields
        $this->newPaymentAmount = '';
        $this->newPaymentDate = '';
        $this->newPaymentMethod = null;
        $this->newPaymentReference = null;
        $this->newPaymentNotes = null;

        $this->success(__('app.payments.payment_added'));
    }

    /**
     * Delete a payment record and recompute the invoice payment status.
     */
    public function deletePayment(int $paymentId): void
    {
        $invoice = $this->getPayableInvoice();

        Payment::where('id', $paymentId)
            ->where('invoice_id', $invoice->id)
            ->delete();

        $invoice->refresh();
        $invoice->recalculatePaymentStatus();

        $this->success(__('app.payments.payment_deleted'));
    }

    /**
     * Record a single payment for the full remaining balance (quick "mark as paid" action).
     */
    public function markAsPaid(): void
    {
        $invoice = $this->getPayableInvoice();
        $remaining = $invoice->remainingBalance();

        if ($remaining <= 0) {
            return;
        }

        Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => $remaining,
            'paid_at' => now()->format('Y-m-d'),
            'payment_method' => $invoice->payment_method ?: null,
        ]);

        $invoice->refresh();
        $invoice->recalculatePaymentStatus();

        $this->success(__('app.payments.payment_added'));
    }

    /**
     * Returns the payment method options for the select dropdown.
     *
     * @return array<array{id: string, name: string}>
     */
    public function getPaymentMethodOptionsProperty(): array
    {
        return collect(PaymentMethod::cases())
            ->map(fn ($method) => ['id' => $method->value, 'name' => $method->label()])
            ->all();
    }

    /**
     * Returns the payments for this invoice, ordered by date descending.
     */
    public function getInvoicePaymentsProperty(): Collection
    {
        return $this->getPayableInvoice()
            ->payments()
            ->orderByDesc('paid_at')
            ->get();
    }

    /**
     * Returns the invoice model being edited.
     * Must be implemented by the host component.
     */
    abstract protected function getPayableInvoice(): Model;
}
