<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ItalianVatNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return;
        }

        // Strip optional "IT" prefix
        $vat = preg_replace('/^IT/i', '', $value);

        if (! preg_match('/^\d{11}$/', $vat)) {
            $fail(__('validation.italian_vat_number'));

            return;
        }

        if (! self::isValidCheckDigit($vat)) {
            $fail(__('validation.italian_vat_number'));
        }
    }

    /**
     * Luhn-variant algorithm for Italian VAT numbers (Partita IVA).
     * Odd positions (1-indexed): sum digits as-is.
     * Even positions: double each digit, subtract 9 if >= 10, then sum.
     * Check digit = (10 - total % 10) % 10.
     */
    public static function isValidCheckDigit(string $vat): bool
    {
        $digits = array_map('intval', str_split($vat));
        $oddSum = 0;
        $evenSum = 0;

        for ($i = 0; $i < 10; $i++) {
            if ($i % 2 === 0) {
                $oddSum += $digits[$i];
            } else {
                $doubled = $digits[$i] * 2;
                $evenSum += $doubled >= 10 ? $doubled - 9 : $doubled;
            }
        }

        $expected = (10 - ($oddSum + $evenSum) % 10) % 10;

        return $digits[10] === $expected;
    }
}
