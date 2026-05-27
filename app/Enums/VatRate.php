<?php

namespace App\Enums;

enum VatRate: string
{
    // Standard VAT rates
    case R22 = 'R22';
    case R10 = 'R10';
    case R5 = 'R5';
    case R4 = 'R4';

    // N1 - Excluded from VAT (art. 15)
    case N1 = 'N1';

    // N2 - Not subject to VAT
    case N2_1 = 'N2.1';
    case N2_2 = 'N2.2';

    // N3 - Not taxable (exports and similar)
    case N3_1 = 'N3.1';
    case N3_2 = 'N3.2';
    case N3_3 = 'N3.3';
    case N3_4 = 'N3.4';
    case N3_5 = 'N3.5';
    case N3_6 = 'N3.6';

    // N4 - Exempt
    case N4 = 'N4';

    // N5 - Margin scheme
    case N5 = 'N5';

    // N6 - Reverse charge
    case N6_1 = 'N6.1';
    case N6_2 = 'N6.2';
    case N6_3 = 'N6.3';
    case N6_4 = 'N6.4';
    case N6_5 = 'N6.5';
    case N6_6 = 'N6.6';
    case N6_7 = 'N6.7';
    case N6_8 = 'N6.8';
    case N6_9 = 'N6.9';

    // N7 - VAT paid in another EU country
    case N7 = 'N7';

    /**
     * VAT percentage (0.00 for all nature codes).
     */
    public function percent(): float
    {
        return match ($this) {
            self::R22 => 22.00,
            self::R10 => 10.00,
            self::R5 => 5.00,
            self::R4 => 4.00,
            default => 0.00,
        };
    }

    /**
     * SDI natura code, or null for standard taxed rates.
     */
    public function nature(): ?string
    {
        // Standard rates (R22, R10, R5, R4) have no natura code
        if (str_starts_with($this->value, 'R')) {
            return null;
        }

        return $this->value;
    }

    /**
     * Human-readable label for display in dropdowns.
     */
    public function label(): string
    {
        return match ($this) {
            self::R22 => '22% - Aliquota Ordinaria',
            self::R10 => '10% - Aliquota Ridotta',
            self::R5 => '5% - Aliquota Ridotta Speciale',
            self::R4 => '4% - Aliquota Minima',
            self::N1 => '0% N1 - Escluse ex art. 15',
            self::N2_1 => '0% N2.1 - Non soggette ex artt. da 7 a 7-septies',
            self::N2_2 => '0% N2.2 - Non soggette - altri casi',
            self::N3_1 => '0% N3.1 - Non imponibili - esportazioni',
            self::N3_2 => '0% N3.2 - Non imponibili - cessioni intracomunitarie',
            self::N3_3 => '0% N3.3 - Non imponibili - cessioni verso San Marino',
            self::N3_4 => '0% N3.4 - Non imponibili - operazioni assimilate alle esportazioni',
            self::N3_5 => '0% N3.5 - Non imponibili - a seguito di dichiarazioni d\'intento',
            self::N3_6 => '0% N3.6 - Non imponibili - altre operazioni che non concorrono alla formazione del plafond',
            self::N4 => '0% N4 - Esenti',
            self::N5 => '0% N5 - Regime del margine',
            self::N6_1 => '0% N6.1 - Inversione contabile - cessione di rottami',
            self::N6_2 => '0% N6.2 - Inversione contabile - cessione di oro e argento',
            self::N6_3 => '0% N6.3 - Inversione contabile - subappalto settore edile',
            self::N6_4 => '0% N6.4 - Inversione contabile - cessione di fabbricati',
            self::N6_5 => '0% N6.5 - Inversione contabile - cessione di telefoni cellulari',
            self::N6_6 => '0% N6.6 - Inversione contabile - cessione di prodotti elettronici',
            self::N6_7 => '0% N6.7 - Inversione contabile - prestazioni settore edile',
            self::N6_8 => '0% N6.8 - Inversione contabile - operazioni settore energetico',
            self::N6_9 => '0% N6.9 - Inversione contabile - altri casi',
            self::N7 => '0% N7 - IVA assolta in altro stato UE',
        };
    }

    /**
     * Options array for select dropdowns, ordered by percent desc then natura code.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public static function options(): array
    {
        $cases = self::cases();

        usort($cases, function (self $a, self $b) {
            $percentDiff = $b->percent() <=> $a->percent();
            if ($percentDiff !== 0) {
                return $percentDiff;
            }

            return strcmp($a->value, $b->value);
        });

        return array_map(
            fn (self $rate) => ['id' => $rate->value, 'name' => $rate->label()],
            $cases
        );
    }
}
