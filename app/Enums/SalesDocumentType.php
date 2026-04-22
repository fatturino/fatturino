<?php

namespace App\Enums;

enum SalesDocumentType: string
{
    case TD01 = 'TD01';
    case TD02 = 'TD02';
    case TD03 = 'TD03';
    case TD04 = 'TD04';
    case TD06 = 'TD06';
    case TD24 = 'TD24';
    case TD25 = 'TD25';

    public function label(): string
    {
        return match ($this) {
            self::TD01 => 'Fattura',
            self::TD02 => 'Acconto/anticipo su fattura',
            self::TD03 => 'Acconto/anticipo su parcella',
            self::TD04 => 'Nota di Credito',
            self::TD06 => 'Parcella',
            self::TD24 => 'Fattura differita',
            self::TD25 => 'Fattura differita (art. 21, c. 6, lett. a)',
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
            fn (self $type) => ['id' => $type->value, 'name' => $type->value . ' - ' . $type->label()],
            self::cases()
        );
    }
}
