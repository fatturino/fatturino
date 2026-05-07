<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case MP01 = 'MP01';
    case MP02 = 'MP02';
    case MP03 = 'MP03';
    case MP04 = 'MP04';
    case MP05 = 'MP05';
    case MP06 = 'MP06';
    case MP07 = 'MP07';
    case MP08 = 'MP08';
    case MP09 = 'MP09';
    case MP10 = 'MP10';
    case MP11 = 'MP11';
    case MP12 = 'MP12';
    case MP13 = 'MP13';
    case MP14 = 'MP14';
    case MP15 = 'MP15';
    case MP16 = 'MP16';
    case MP17 = 'MP17';
    case MP18 = 'MP18';
    case MP19 = 'MP19';
    case MP20 = 'MP20';
    case MP21 = 'MP21';
    case MP22 = 'MP22';
    case MP23 = 'MP23';

    public function label(): string
    {
        return match ($this) {
            self::MP01 => 'Contanti',
            self::MP02 => 'Assegno',
            self::MP03 => 'Assegno circolare',
            self::MP04 => 'Contanti presso Tesoreria',
            self::MP05 => 'Bonifico bancario',
            self::MP06 => 'Vaglia cambiario',
            self::MP07 => 'Bollettino bancario',
            self::MP08 => 'Carte di pagamento',
            self::MP09 => 'RID',
            self::MP10 => 'RID utenze',
            self::MP11 => 'RID veloce',
            self::MP12 => 'RIBA',
            self::MP13 => 'MAV',
            self::MP14 => 'Quietanza erario',
            self::MP15 => 'Giroconto su conti di contabilita speciale',
            self::MP16 => 'Domiciliazione bancaria',
            self::MP17 => 'Domiciliazione postale',
            self::MP18 => 'Bollettino di c/c postale',
            self::MP19 => 'SEPA Direct Debit',
            self::MP20 => 'SEPA Direct Debit CORE',
            self::MP21 => 'SEPA Direct Debit B2B',
            self::MP22 => 'Trattenuta su somme gia riscosse',
            self::MP23 => 'PagoPA',
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
            fn (self $method) => ['id' => $method->value, 'name' => $method->value.' - '.$method->label()],
            self::cases()
        );
    }
}
