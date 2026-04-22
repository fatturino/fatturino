<?php

namespace App\Enums;

enum ProformaStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Converted = 'converted';
    case Cancelled = 'cancelled';

    /**
     * Translated label for display
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft     => __('app.proforma.status_draft'),
            self::Sent      => __('app.proforma.status_sent'),
            self::Converted => __('app.proforma.status_converted'),
            self::Cancelled => __('app.proforma.status_cancelled'),
        };
    }

    /**
     * Badge color class for Mary UI / DaisyUI
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft     => 'badge-soft badge-warning',
            self::Sent      => 'badge-soft badge-info',
            self::Converted => 'badge-soft badge-success',
            self::Cancelled => 'badge-soft badge-neutral',
        };
    }

    /**
     * Icon for Mary UI badge
     */
    public function icon(): string
    {
        return match ($this) {
            self::Draft     => 'o-pencil-square',
            self::Sent      => 'o-paper-airplane',
            self::Converted => 'o-document-check',
            self::Cancelled => 'o-x-circle',
        };
    }
}
