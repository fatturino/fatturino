<?php

namespace App\Support;

class FiscalRegimePolicy
{
    public const FORFETTARIO_VAT_RATE = 'N2.2';

    public const FORFETTARIO_VAT_NOTICE = "Operazione in franchigia da IVA ai sensi dell'art. 1, commi 54-89, Legge 190/2014";

    public const FORFETTARIO_WITHHOLDING_NOTICE = "Compenso non soggetto a ritenuta d'acconto ai sensi dell'art. 1, comma 67, Legge 190/2014";

    public static function supportsWithholdingTax(?string $fiscalRegime): bool
    {
        return $fiscalRegime !== 'RF19';
    }

    public static function supportsVatOperations(?string $fiscalRegime): bool
    {
        return $fiscalRegime !== 'RF19';
    }

    public static function supportsSelfInvoices(?string $fiscalRegime, bool $rf19SelfInvoicesEnabled): bool
    {
        if ($fiscalRegime !== 'RF19') {
            return true;
        }

        return $rf19SelfInvoicesEnabled;
    }

    public static function requiresForfettarioLegalNotice(?string $fiscalRegime): bool
    {
        return $fiscalRegime === 'RF19';
    }

    public static function normalizeDocumentPayload(array $payload, ?string $fiscalRegime): array
    {
        if ($fiscalRegime !== 'RF19') {
            return $payload;
        }

        $payload['withholding_tax_enabled'] = false;
        $payload['withholding_tax_percent'] = null;
        $payload['split_payment'] = false;
        $payload['vat_payability'] = 'I';
        $payload['notes'] = self::appendForfettarioLegalNotices($payload['notes'] ?? null);

        return $payload;
    }

    public static function normalizeInvoiceSettingsPayload(array $payload, ?string $fiscalRegime): array
    {
        if ($fiscalRegime !== 'RF19') {
            return $payload;
        }

        $payload['withholding_tax_enabled'] = false;
        $payload['default_split_payment'] = false;
        $payload['default_vat_payability'] = 'I';
        $payload['auto_stamp_duty'] = true;

        return $payload;
    }

    public static function normalizeLinesForForfettario(array $lines, ?string $fiscalRegime): array
    {
        if ($fiscalRegime !== 'RF19') {
            return $lines;
        }

        return array_map(function (array $line): array {
            $line['vat_rate'] = self::FORFETTARIO_VAT_RATE;

            return $line;
        }, $lines);
    }

    public static function appendForfettarioLegalNotices(?string $notes): string
    {
        $base = trim((string) $notes);
        $lines = $base === '' ? [] : preg_split('/\r\n|\r|\n/', $base);
        $lines = array_values(array_filter(array_map('trim', $lines), fn (string $line): bool => $line !== ''));

        self::appendMissingNoticeLine($lines, self::FORFETTARIO_VAT_NOTICE);
        self::appendMissingNoticeLine($lines, self::FORFETTARIO_WITHHOLDING_NOTICE);

        return implode("\n", $lines);
    }

    private static function appendMissingNoticeLine(array &$lines, string $notice): void
    {
        foreach ($lines as $line) {
            if (mb_strtolower($line) === mb_strtolower($notice)) {
                return;
            }
        }

        $lines[] = $notice;
    }
}
