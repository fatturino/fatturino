<?php

namespace App\Enums;

enum VatPayability: string
{
    case I = 'I';
    case D = 'D';
    case S = 'S';

    public function label(): string
    {
        return match ($this) {
            self::I => 'Immediata',
            self::D => 'Differita',
            self::S => 'Scissione dei pagamenti',
        };
    }

    /**
     * Options array for select dropdowns.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $v) => ['id' => $v->value, 'name' => $v->value.' - '.$v->label()],
            self::cases()
        );
    }
}
