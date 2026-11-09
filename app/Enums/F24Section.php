<?php

namespace App\Enums;

enum F24Section: string
{
    case Erario = 'erario';
    case Inps = 'inps';
    case Regioni = 'regioni';
    case Imu = 'imu';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Erario => __('app.f24.section_erario'),
            self::Inps => __('app.f24.section_inps'),
            self::Regioni => __('app.f24.section_regioni'),
            self::Imu => __('app.f24.section_imu'),
            self::Other => __('app.f24.section_other'),
        };
    }
}
