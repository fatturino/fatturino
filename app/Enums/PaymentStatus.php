<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
    case Overdue = 'overdue';

    /**
     * Translated label for display
     */
    public function label(): string
    {
        return match ($this) {
            self::Unpaid => __('app.invoices.payment_status_unpaid'),
            self::Partial => __('app.invoices.payment_status_partial'),
            self::Paid => __('app.invoices.payment_status_paid'),
            self::Overdue => __('app.invoices.payment_status_overdue'),
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Unpaid => 'warning',
            self::Partial => 'info',
            self::Paid => 'success',
            self::Overdue => 'danger',
        };
    }

    /**
     * Badge color class for Mary UI / DaisyUI (deprecated, use badgeVariant)
     */
    public function color(): string
    {
        return match ($this) {
            self::Unpaid => 'badge-soft badge-warning',
            self::Partial => 'badge-soft badge-info',
            self::Paid => 'badge-soft badge-success',
            self::Overdue => 'badge-soft badge-error',
        };
    }

    /**
     * Icon for Mary UI badge
     */
    public function icon(): string
    {
        return match ($this) {
            self::Unpaid => 'o-clock',
            self::Partial => 'o-arrows-right-left',
            self::Paid => 'o-check-circle',
            self::Overdue => 'o-exclamation-triangle',
        };
    }
}
