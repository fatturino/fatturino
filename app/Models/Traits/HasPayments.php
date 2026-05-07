<?php

namespace App\Models\Traits;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasPayments
{
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'invoice_id');
    }

    /**
     * The amount still owed after all registered payments, in cents.
     */
    public function remainingBalance(): int
    {
        return max(0, $this->total_gross - $this->total_paid);
    }

    /**
     * Recompute payment_status and total_paid from the actual payments records.
     * Called after any payment is created or deleted.
     */
    public function recalculatePaymentStatus(): void
    {
        $totalPaid = $this->payments()->sum('amount');

        $status = match (true) {
            $totalPaid >= $this->total_gross && $this->total_gross > 0 => PaymentStatus::Paid,
            $totalPaid > 0 && $this->due_date?->isPast() => PaymentStatus::Overdue,
            $totalPaid > 0 => PaymentStatus::Partial,
            $this->due_date?->isPast() => PaymentStatus::Overdue,
            default => PaymentStatus::Unpaid,
        };

        $this->update([
            'payment_status' => $status,
            'total_paid' => $totalPaid,
        ]);
    }
}
