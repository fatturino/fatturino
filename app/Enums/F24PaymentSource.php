<?php

namespace App\Enums;

enum F24PaymentSource: string
{
    case Manual = 'manual';
    case Ocr = 'ocr';

    public function label(): string
    {
        return match ($this) {
            self::Manual => __('app.f24.source_manual'),
            self::Ocr => __('app.f24.source_ocr'),
        };
    }
}
