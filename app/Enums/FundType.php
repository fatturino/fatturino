<?php

namespace App\Enums;

enum FundType: string
{
    case TC01 = 'TC01';
    case TC02 = 'TC02';
    case TC03 = 'TC03';
    case TC04 = 'TC04';
    case TC05 = 'TC05';
    case TC06 = 'TC06';
    case TC07 = 'TC07';
    case TC08 = 'TC08';
    case TC09 = 'TC09';
    case TC10 = 'TC10';
    case TC11 = 'TC11';
    case TC12 = 'TC12';
    case TC13 = 'TC13';
    case TC14 = 'TC14';
    case TC15 = 'TC15';
    case TC16 = 'TC16';
    case TC17 = 'TC17';
    case TC18 = 'TC18';
    case TC19 = 'TC19';
    case TC20 = 'TC20';
    case TC21 = 'TC21';
    case TC22 = 'TC22';

    public function label(): string
    {
        return match ($this) {
            self::TC01 => 'Cassa Nazionale Forense (Avvocati)',
            self::TC02 => 'Cassa Previdenza Dottori Commercialisti',
            self::TC03 => 'INARCASSA (Ingegneri/Architetti)',
            self::TC04 => 'ENPAM (Medici/Odontoiatri)',
            self::TC05 => 'CNPADC (Dottori Commercialisti)',
            self::TC06 => 'CNPR (Ragionieri/Periti Commerciali)',
            self::TC07 => 'ENPACL (Consulenti del Lavoro)',
            self::TC08 => 'EPAP (Pluricategoriale)',
            self::TC09 => 'ENPAP (Psicologi)',
            self::TC10 => 'EPPI (Periti Industriali)',
            self::TC11 => 'ENPAV (Veterinari)',
            self::TC12 => 'ENPAIA (Agrotecnici)',
            self::TC13 => 'FASC (Spedizionieri)',
            self::TC14 => 'ENPAB (Biologi)',
            self::TC15 => 'CIPAG (Geometri)',
            self::TC16 => 'ENASARCO',
            self::TC17 => 'ENPAPI (Infermieri)',
            self::TC18 => 'ENPAF (Farmacisti)',
            self::TC19 => 'ENPAIA (Periti Agrari)',
            self::TC20 => 'ENPA (Agronomi/Forestali)',
            self::TC21 => 'INPGI (Giornalisti)',
            self::TC22 => 'INPS Gestione Separata',
        };
    }

    /**
     * Default contribution percentage for each fund type.
     */
    public function defaultPercent(): string
    {
        return match ($this) {
            self::TC04, self::TC09, self::TC11 => '2.00',
            self::TC10 => '5.00',
            default => '4.00',
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
