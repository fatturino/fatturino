<?php

namespace App\Livewire\Settings;

use App\Enums\FundType;
use App\Enums\VatRate;
use App\Models\Sequence;
use App\Settings\InvoiceSettings;
use App\Traits\Toast;
use Livewire\Component;

class Invoice extends Component
{
    use Toast;

    public ?int $default_sequence_sales = null;

    public ?string $default_vat_rate = null;

    public bool $withholding_tax_enabled = false;

    public string $withholding_tax_percent = '20.00';

    public bool $fund_enabled = false;

    public ?string $fund_type = null;

    public string $fund_percent = '4.00';

    public ?string $fund_vat_rate = null;

    public bool $fund_has_deduction = false;

    public bool $auto_stamp_duty = false;

    public string $stamp_duty_threshold = '77.47';

    public ?string $default_payment_method = null;

    public ?string $default_payment_terms = null;

    public ?string $default_bank_name = null;

    public ?string $default_bank_iban = null;

    public string $default_vat_payability = 'I';

    public bool $default_split_payment = false;

    public ?string $default_notes = null;

    public function mount(InvoiceSettings $settings)
    {
        $this->default_sequence_sales = $settings->default_sequence_sales;
        $this->default_vat_rate = $settings->default_vat_rate?->value;
        $this->withholding_tax_enabled = $settings->withholding_tax_enabled;
        $this->withholding_tax_percent = $settings->withholding_tax_percent;
        $this->fund_enabled = $settings->fund_enabled;
        $this->fund_type = $settings->fund_type;
        $this->fund_percent = $settings->fund_percent;
        $this->fund_vat_rate = $settings->fund_vat_rate?->value;
        $this->fund_has_deduction = $settings->fund_has_deduction;
        $this->auto_stamp_duty = $settings->auto_stamp_duty;
        $this->stamp_duty_threshold = $settings->stamp_duty_threshold;
        $this->default_payment_method = $settings->default_payment_method;
        $this->default_payment_terms = $settings->default_payment_terms;
        $this->default_bank_name = $settings->default_bank_name;
        $this->default_bank_iban = $settings->default_bank_iban;
        $this->default_vat_payability = $settings->default_vat_payability ?? 'I';
        $this->default_split_payment = $settings->default_split_payment ?? false;
        $this->default_notes = $settings->default_notes;
    }

    // Auto-fill fund percentage when fund type changes
    public function updatedFundType()
    {
        if ($this->fund_type) {
            $type = FundType::tryFrom($this->fund_type);
            if ($type) {
                $this->fund_percent = $type->defaultPercent();
            }
        }
    }

    public function save(InvoiceSettings $settings)
    {
        $settings->default_sequence_sales = $this->default_sequence_sales;
        $settings->default_vat_rate = VatRate::tryFrom($this->default_vat_rate ?? '');
        $settings->withholding_tax_enabled = $this->withholding_tax_enabled;
        $settings->withholding_tax_percent = $this->withholding_tax_percent;
        $settings->fund_enabled = $this->fund_enabled;
        $settings->fund_type = $this->fund_type;
        $settings->fund_percent = $this->fund_percent;
        $settings->fund_vat_rate = VatRate::tryFrom($this->fund_vat_rate ?? '');
        $settings->fund_has_deduction = $this->fund_has_deduction;
        $settings->auto_stamp_duty = $this->auto_stamp_duty;
        $settings->stamp_duty_threshold = $this->stamp_duty_threshold;
        $settings->default_payment_method = $this->default_payment_method;
        $settings->default_payment_terms = $this->default_payment_terms;
        $settings->default_bank_name = $this->default_bank_name;
        $settings->default_bank_iban = $this->default_bank_iban;
        $settings->default_vat_payability = $this->default_vat_payability;
        $settings->default_split_payment = $this->default_split_payment;
        $settings->default_notes = $this->default_notes;

        $settings->save();

        $this->success(__('app.settings.invoice.saved'));
    }

    public function render()
    {
        return view('livewire.settings.invoice', [
            'sequences' => Sequence::all(),
            'vatRates' => VatRate::options(),
        ]);
    }
}
