<?php

namespace App\Livewire\Settings;

use App\Contracts\EnvironmentCapabilities;
use App\Enums\AtecoCode;
use App\Enums\Capability;
use App\Rules\ItalianTaxCode;
use App\Rules\ItalianVatNumber;
use App\Settings\CompanySettings;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

class Company extends Component
{
    use Toast, WithFileUploads;

    #[Validate('required')]
    public string $company_name = '';

    #[Validate(['nullable', new ItalianVatNumber])]
    public ?string $company_vat_number = null;

    #[Validate(['nullable', new ItalianTaxCode])]
    public ?string $company_tax_code = null;

    public ?string $company_address = null;

    public ?string $company_city = null;

    public ?string $company_postal_code = null;

    public ?string $company_province = null;

    public string $company_country = 'IT';

    public ?string $company_pec = null;

    public ?string $company_sdi_code = null;

    #[Validate('required|in:RF01,RF19')]
    public string $company_fiscal_regime = 'RF01';

    // Logo upload (temporary Livewire file object during upload)
    #[Validate(['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:1024', 'dimensions:max_width=4000,max_height=4000'])]
    public $company_logo = null;

    // Path of the existing logo stored on the public disk
    public ?string $company_logo_path = null;

    // Selected ATECO division codes (array of string values from AtecoCode enum)
    public array $company_ateco_codes = [];

    // Current search string used to filter the ATECO dropdown options
    public string $ateco_search = '';

    // True when the current environment blocks editing (e.g., demo mode plugin)
    public bool $readonly = false;

    public function mount(CompanySettings $settings, EnvironmentCapabilities $capabilities)
    {
        $this->readonly = $capabilities->cannot(Capability::EditCompanySettings);
        $this->company_name = $settings->company_name;
        $this->company_vat_number = $settings->company_vat_number;
        $this->company_tax_code = $settings->company_tax_code;
        $this->company_address = $settings->company_address;
        $this->company_city = $settings->company_city;
        $this->company_postal_code = $settings->company_postal_code;
        $this->company_province = $settings->company_province;
        $this->company_country = $settings->company_country;
        $this->company_pec = $settings->company_pec;
        $this->company_sdi_code = $settings->company_sdi_code;
        $this->company_fiscal_regime = $settings->company_fiscal_regime ?: 'RF01';
        $this->company_logo_path = $settings->company_logo_path;
        $this->company_ateco_codes = $settings->company_ateco_codes ?? [];
    }

    /**
     * Returns ATECO 2025 search results, excluding already-selected codes.
     * Returns empty list until at least 2 characters are typed.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function atecoSearchResults(): array
    {
        if (mb_strlen(trim($this->ateco_search)) < 2) {
            return [];
        }

        $results = AtecoCode::options(search: $this->ateco_search);

        return array_values(array_filter(
            $results,
            fn (array $opt) => ! in_array($opt['id'], $this->company_ateco_codes, true)
        ));
    }

    /**
     * Adds an ATECO code to the selected list, avoiding duplicates.
     */
    public function addAtecoCode(string $codice): void
    {
        if (! in_array($codice, $this->company_ateco_codes, true)) {
            $this->company_ateco_codes[] = $codice;
        }

        $this->ateco_search = '';
    }

    /**
     * Removes an ATECO code from the selected list.
     */
    public function removeAtecoCode(string $codice): void
    {
        $this->company_ateco_codes = array_values(
            array_filter($this->company_ateco_codes, fn (string $c) => $c !== $codice)
        );
    }

    public function save(CompanySettings $settings, EnvironmentCapabilities $capabilities)
    {
        if ($capabilities->cannot(Capability::EditCompanySettings)) {
            $this->error(__('app.settings.company.readonly_error'));

            return;
        }

        $this->validate();

        $settings->company_name = $this->company_name;
        $settings->company_vat_number = $this->company_vat_number ?? '';
        $settings->company_tax_code = $this->company_tax_code ?? '';
        $settings->company_address = $this->company_address ?? '';
        $settings->company_city = $this->company_city ?? '';
        $settings->company_postal_code = $this->company_postal_code ?? '';
        $settings->company_province = $this->company_province ?? '';
        $settings->company_country = $this->company_country;
        $settings->company_pec = $this->company_pec ?? '';
        $settings->company_sdi_code = $this->company_sdi_code ?? '';
        $settings->company_fiscal_regime = $this->company_fiscal_regime;
        $settings->company_ateco_codes = empty($this->company_ateco_codes) ? null : $this->company_ateco_codes;

        // Store the new logo if one was uploaded
        if ($this->company_logo) {
            // Delete the previous logo file if it exists
            if ($settings->company_logo_path) {
                Storage::disk('public')->delete($settings->company_logo_path);
            }

            $extension = $this->company_logo->extension();
            $path = $this->company_logo->storeAs('logos', 'company-logo.'.$extension, 'public');
            $settings->company_logo_path = $path;
            $this->company_logo_path = $path;
        }

        $settings->save();

        $this->success(__('app.settings.company.saved'));
    }

    public function removeLogo(CompanySettings $settings, EnvironmentCapabilities $capabilities): void
    {
        if ($capabilities->cannot(Capability::EditCompanySettings)) {
            $this->error(__('app.settings.company.readonly_error'));

            return;
        }

        if ($settings->company_logo_path) {
            Storage::disk('public')->delete($settings->company_logo_path);
        }

        $settings->company_logo_path = null;
        $settings->save();

        $this->company_logo_path = null;
        $this->company_logo = null;

        $this->success(__('app.settings.company.logo_removed'));
    }

    public function fiscalRegimes(): array
    {
        return [
            ['id' => 'RF01', 'name' => 'RF01 — Regime Ordinario'],
            ['id' => 'RF19', 'name' => 'RF19 — Regime Forfettario'],
        ];
    }

    public function render()
    {
        return view('livewire.settings.company', [
            'fiscalRegimes' => $this->fiscalRegimes(),
        ]);
    }
}
