<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ItalianTaxCode implements ValidationRule
{
    // Odd-position character values (1-indexed positions: 1,3,5,...,15)
    private const ODD_VALUES = [
        '0' => 1, '1' => 0, '2' => 5, '3' => 7, '4' => 9,
        '5' => 13, '6' => 15, '7' => 17, '8' => 19, '9' => 21,
        'A' => 1, 'B' => 0, 'C' => 5, 'D' => 7, 'E' => 9,
        'F' => 13, 'G' => 15, 'H' => 17, 'I' => 19, 'J' => 21,
        'K' => 2, 'L' => 4, 'M' => 18, 'N' => 20, 'O' => 11,
        'P' => 3, 'Q' => 6, 'R' => 8, 'S' => 12, 'T' => 14,
        'U' => 16, 'V' => 10, 'W' => 22, 'X' => 25, 'Y' => 24,
        'Z' => 23,
    ];

    // Even-position character values (1-indexed positions: 2,4,6,...,14)
    private const EVEN_VALUES = [
        '0' => 0, '1' => 1, '2' => 2, '3' => 3, '4' => 4,
        '5' => 5, '6' => 6, '7' => 7, '8' => 8, '9' => 9,
        'A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4,
        'F' => 5, 'G' => 6, 'H' => 7, 'I' => 8, 'J' => 9,
        'K' => 10, 'L' => 11, 'M' => 12, 'N' => 13, 'O' => 14,
        'P' => 15, 'Q' => 16, 'R' => 17, 'S' => 18, 'T' => 19,
        'U' => 20, 'V' => 21, 'W' => 22, 'X' => 23, 'Y' => 24,
        'Z' => 25,
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return;
        }

        $code = strtoupper(trim($value));

        // Numeric 11-digit code (companies) uses same algorithm as Partita IVA
        if (preg_match('/^\d{11}$/', $code)) {
            if (! ItalianVatNumber::isValidCheckDigit($code)) {
                $fail(__('validation.italian_tax_code'));
            }

            return;
        }

        // Alphanumeric 16-character code (individuals)
        if (! preg_match('/^[A-Z]{6}\d{2}[A-Z]\d{2}[A-Z]\d{3}[A-Z]$/', $code)) {
            $fail(__('validation.italian_tax_code'));

            return;
        }

        if (! $this->isValidPersonalCheckChar($code)) {
            $fail(__('validation.italian_tax_code'));
        }
    }

    /**
     * Validate the 16th check character of a personal Codice Fiscale.
     * Sum odd-position and even-position values, mod 26 gives the check letter.
     */
    private function isValidPersonalCheckChar(string $code): bool
    {
        $sum = 0;

        for ($i = 0; $i < 15; $i++) {
            $char = $code[$i];
            // 1-indexed: positions 1,3,5... are odd
            $sum += ($i % 2 === 0)
                ? self::ODD_VALUES[$char]
                : self::EVEN_VALUES[$char];
        }

        $expected = chr(($sum % 26) + ord('A'));

        return $code[15] === $expected;
    }
}
