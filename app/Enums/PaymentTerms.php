<?php

namespace App\Enums;

enum PaymentTerms: string
{
    case TP01 = 'TP01';
    case TP02 = 'TP02';
    case TP03 = 'TP03';

    public function label(): string
    {
        return match ($this) {
            self::TP01 => 'A rate',
            self::TP02 => 'Pagamento completo',
            self::TP03 => 'Anticipo',
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
            fn (self $term) => ['id' => $term->value, 'name' => $term->value . ' - ' . $term->label()],
            self::cases()
        );
    }
}
