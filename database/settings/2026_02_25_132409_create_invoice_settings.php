<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Default sequences for each invoice category
        $this->migrator->add('invoice.default_sequence_sales', null);
        $this->migrator->add('invoice.default_sequence_purchase', null);
        $this->migrator->add('invoice.default_sequence_quote', null);
        $this->migrator->add('invoice.default_sequence_credit_notes', null);
        $this->migrator->add('invoice.default_sequence_proforma', null);
        $this->migrator->add('invoice.default_sequence_self_invoice', null);

        // VAT and tax defaults
        $this->migrator->add('invoice.default_vat_rate', null);
        $this->migrator->add('invoice.withholding_tax_enabled', false);

        // Default withholding tax percentage (Italian standard: 20%)
        $this->migrator->add('invoice.withholding_tax_percent', '20.00');

        // Stamp duty (Marca da bollo)
        $this->migrator->add('invoice.auto_stamp_duty', false);
        $this->migrator->add('invoice.stamp_duty_threshold', '77.47');

        // Payment defaults
        $this->migrator->add('invoice.default_payment_method', null);
        $this->migrator->add('invoice.default_payment_terms', null);

        // VAT payability (Esigibilità IVA)
        $this->migrator->add('invoice.default_vat_payability', 'I');
        $this->migrator->add('invoice.default_split_payment', false);

        // Bank details
        $this->migrator->add('invoice.default_bank_name', null);
        $this->migrator->add('invoice.default_bank_iban', null);

        // Miscellaneous
        $this->migrator->add('invoice.default_notes', null);
        $this->migrator->add('invoice.yearly_numbering_reset', true);

        // Professional fund (Cassa Previdenziale)
        $this->migrator->add('invoice.fund_enabled', false);
        $this->migrator->add('invoice.fund_type', null);
        $this->migrator->add('invoice.fund_percent', '4.00');
        $this->migrator->add('invoice.fund_vat_rate', null);
        $this->migrator->add('invoice.fund_has_deduction', false);
    }
};
