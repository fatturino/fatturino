<?php

namespace App\Enums;

enum F24PaymentStatus: string
{
    case Draft = 'draft';
    case Planned = 'planned';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('app.f24.status_draft'),
            self::Planned => __('app.f24.status_planned'),
            self::Paid => __('app.f24.status_paid'),
            self::Cancelled => __('app.f24.status_cancelled'),
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Planned => 'warning',
            self::Paid => 'success',
            self::Cancelled => 'danger',
        };
    }
}
