<?php

namespace App\Livewire\Setup;

use App\Models\User;
use App\Settings\CompanySettings;
use App\Settings\InvoiceSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class Wizard extends Component
{
    public int $step = 1;

    public const TOTAL_STEPS = 3;

    // Step 1 — Account
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    // Step 2 — Company info
    public string $company_name = '';

    public string $company_vat_number = '';

    public string $company_tax_code = '';

    public string $company_fiscal_regime = 'RF01';

    // Step 2 — Invoice defaults
    public bool $withholding_tax_enabled = false;

    public bool $auto_stamp_duty = false;

    // Step 3 — Address & electronic invoicing
    public string $company_address = '';

    public string $company_city = '';

    public string $company_postal_code = '';

    public string $company_province = '';

    public string $company_country = 'IT';

    public string $company_pec = '';

    public string $company_sdi_code = '0000000';

    // Step 3 — Legal storage acknowledgment (AdE conservazione service)
    public bool $conservation_acknowledged = false;

    public function mount(CompanySettings $settings): void
    {
        // If at least one user exists, setup is already done
        if (User::count() > 0) {
            $this->redirectRoute('login');

            return;
        }

        // Pre-fill with existing settings values as hints
        $this->company_name = $settings->company_name;
        $this->company_vat_number = $settings->company_vat_number;
        $this->company_tax_code = $settings->company_tax_code;
        $this->company_fiscal_regime = $settings->company_fiscal_regime ?: 'RF01';
        $this->company_address = $settings->company_address;
        $this->company_city = $settings->company_city;
        $this->company_postal_code = $settings->company_postal_code;
        $this->company_province = $settings->company_province;
        $this->company_country = $settings->company_country ?: 'IT';
        $this->company_pec = $settings->company_pec;
        $this->company_sdi_code = $settings->company_sdi_code ?: '0000000';
        $this->conservation_acknowledged = $settings->conservation_acknowledged ?? false;
    }

    public function updatedName(string $value): void
    {
        $this->company_name = $value;
    }

    public function updatedCompanyFiscalRegime(string $value): void
    {
        if ($value === 'RF19') {
            $this->auto_stamp_duty = true;
            $this->withholding_tax_enabled = false;
        } elseif ($value === 'RF01') {
            $this->withholding_tax_enabled = true;
            $this->auto_stamp_duty = false;
        }
    }

    public function nextStep(): void
    {
        $this->validateStep($this->step);
        $this->step++;
    }

    public function previousStep(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function complete(CompanySettings $companySettings, InvoiceSettings $invoiceSettings): void
    {
        $this->validateStep($this->step);

        // Create the user. Setup wizard runs only on first install, so this
        // user is always the admin.
        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'is_admin' => true,
        ]);

        // Persist company settings
        $companySettings->company_name = $this->company_name;
        $companySettings->company_vat_number = $this->company_vat_number;
        $companySettings->company_tax_code = $this->company_tax_code;
        $companySettings->company_fiscal_regime = $this->company_fiscal_regime;
        $companySettings->company_address = $this->company_address;
        $companySettings->company_city = $this->company_city;
        $companySettings->company_postal_code = $this->company_postal_code;
        $companySettings->company_province = $this->company_province;
        $companySettings->company_country = $this->company_country;
        $companySettings->company_pec = $this->company_pec;
        $companySettings->company_sdi_code = $this->company_sdi_code;
        $companySettings->conservation_acknowledged = $this->conservation_acknowledged;
        $companySettings->save();

        // Persist invoice defaults (stamp duty & withholding tax)
        $invoiceSettings->withholding_tax_enabled = $this->withholding_tax_enabled;
        $invoiceSettings->auto_stamp_duty = $this->auto_stamp_duty;
        $invoiceSettings->save();

        // Log the user in and send to dashboard
        Auth::login($user);
        session()->regenerate();

        $this->redirectRoute('dashboard', navigate: true);
    }

    /**
     * Returns the Italian fiscal regime options for the select input.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function fiscalRegimes(): array
    {
        return [
            ['id' => 'RF01', 'name' => 'RF01 — Regime Ordinario'],
            ['id' => 'RF19', 'name' => 'RF19 — Regime Forfettario'],
        ];
    }

    private function validateStep(int $step): void
    {
        $rules = match ($step) {
            1 => [
                'name' => 'required|min:2',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|min:8|confirmed',
                'password_confirmation' => 'required',
            ],
            2 => [
                'company_name' => 'required|min:2',
                'company_vat_number' => 'required',
                'company_tax_code' => 'required',
                'company_fiscal_regime' => 'required|in:RF01,RF19',
            ],
            3 => [
                'company_address' => 'required',
                'company_postal_code' => 'required',
                'company_city' => 'required',
                'company_province' => 'required|size:2',
                'company_country' => 'required|size:2',
                'company_pec' => 'required|email',
                'company_sdi_code' => 'required|size:7',
                'conservation_acknowledged' => 'accepted',
            ],
            default => [],
        };

        if (! empty($rules)) {
            $this->validate($rules);
        }
    }

    public function render(): View
    {
        return view('livewire.setup.wizard', [
            'fiscalRegimes' => $this->fiscalRegimes(),
        ]);
    }
}
