<?php

namespace App\Settings;

use App\Enums\VatRate;
use Spatie\LaravelSettings\Settings;

class InvoiceSettings extends Settings
{
    // Default sequences for each category
    public ?int $default_sequence_sales;

    public ?int $default_sequence_purchase;

    public ?int $default_sequence_self_invoice;

    public ?int $default_sequence_quote;

    public ?int $default_sequence_credit_notes;

    public ?int $default_sequence_proforma;

    // VAT and tax defaults
    public ?VatRate $default_vat_rate;

    public bool $withholding_tax_enabled;

    // Default withholding tax percentage (Italian standard: 20%)
    public string $withholding_tax_percent;

    // Professional fund (Cassa Previdenziale)
    public bool $fund_enabled;

    public ?string $fund_type;

    public string $fund_percent;

    public ?VatRate $fund_vat_rate;

    public bool $fund_has_deduction;

    // Stamp duty (Marca da bollo)
    public bool $auto_stamp_duty;

    public string $stamp_duty_threshold;

    // Payment defaults
    public ?string $default_payment_method;

    public ?string $default_payment_terms;

    // VAT payability (Esigibilità IVA)
    public string $default_vat_payability;

    public bool $default_split_payment;

    // Bank details
    public ?string $default_bank_name;

    public ?string $default_bank_iban;

    // Other settings
    public ?string $default_notes;

    public bool $yearly_numbering_reset;

    /**
     * Define the settings group name
     */
    public static function group(): string
    {
        return 'invoice';
    }
}
