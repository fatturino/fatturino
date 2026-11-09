<?php

namespace App\Enums;

enum F24Category: string
{
    case Irpef = 'irpef';
    case Sostitutiva = 'sostitutiva';
    case Iva = 'iva';
    case Inps = 'inps';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Irpef => __('app.f24.category_irpef'),
            self::Sostitutiva => __('app.f24.category_sostitutiva'),
            self::Iva => __('app.f24.category_iva'),
            self::Inps => __('app.f24.category_inps'),
            self::Other => __('app.f24.category_other'),
        };
    }
}
