<?php

namespace App\Enums;

enum FiscalRegime: string
{
    case RF01 = 'RF01';
    case RF02 = 'RF02';
    case RF03 = 'RF03';
    case RF04 = 'RF04';
    case RF05 = 'RF05';
    case RF06 = 'RF06';
    case RF07 = 'RF07';
    case RF08 = 'RF08';
    case RF09 = 'RF09';
    case RF10 = 'RF10';
    case RF11 = 'RF11';
    case RF12 = 'RF12';
    case RF13 = 'RF13';
    case RF14 = 'RF14';
    case RF15 = 'RF15';
    case RF16 = 'RF16';
    case RF17 = 'RF17';
    case RF18 = 'RF18';
    case RF19 = 'RF19';

    public function label(): string
    {
        return match ($this) {
            self::RF01 => 'RF01 - Ordinario',
            self::RF02 => 'RF02 - Minimo (ex regime dei minimi)',
            self::RF03 => 'RF03 - Agevolato (ex regime delle nuove iniziative)',
            self::RF04 => 'RF04 - Agricoltura e attività connesse',
            self::RF05 => 'RF05 - Vendita sali e tabacchi',
            self::RF06 => 'RF06 - Commercio dei fiammiferi',
            self::RF07 => 'RF07 - Editoria',
            self::RF08 => 'RF08 - Gestione di servizi di telefonia pubblica',
            self::RF09 => 'RF09 - Rivendita di documenti di trasporto pubblico',
            self::RF10 => 'RF10 - Intrattenimenti, giochi e altre attività',
            self::RF11 => 'RF11 - Agenzie di viaggio e turismo',
            self::RF12 => 'RF12 - Agriturismo',
            self::RF13 => 'RF13 - Vendite a domicilio',
            self::RF14 => 'RF14 - Rivendita di beni usati, oggetti d\'arte o da collezione',
            self::RF15 => 'RF15 - Agenzie di vendita all\'asta di oggetti d\'arte o da collezione',
            self::RF16 => 'RF16 - IVA per cassa (P.A.)',
            self::RF17 => 'RF17 - IVA per cassa (altri)',
            self::RF18 => 'RF18 - Altro',
            self::RF19 => 'RF19 - Forfettario',
        };
    }

    /**
     * Whether this regime is currently enabled for selection.
     */
    public function isEnabled(): bool
    {
        return in_array($this, [self::RF01, self::RF19]);
    }

    /**
     * Options array for select dropdowns, filtered by enabled status.
     */
    public static function options(bool $onlyEnabled = true): array
    {
        $cases = $onlyEnabled
            ? array_filter(self::cases(), fn (self $r) => $r->isEnabled())
            : self::cases();

        return array_map(
            fn (self $r) => ['value' => $r->value, 'label' => $r->label()],
            array_values($cases)
        );
    }
}
