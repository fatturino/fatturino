<?php

use App\Contracts\EnvironmentCapabilities;
use App\Enums\AtecoCode;
use App\Enums\FiscalRegime;
use App\Rules\ItalianVatNumber;
use App\Settings\CompanySettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::app')] class extends Component {
    use WithFileUploads;

    public string $company_name = '';
    public string $company_vat_number = '';
    public string $company_tax_code = '';
    public string $company_address = '';
    public string $company_postal_code = '';
    public string $company_city = '';
    public string $company_province = '';
    public string $company_country = 'IT';
    public string $company_email = '';
    public string $company_pec = '';
    public string $company_sdi_code = '';
    public string $company_fiscal_regime = 'RF01';
    public bool $rf19_self_invoices_enabled = false;
    public array $company_ateco_codes = [];
    public string $atecoCodesInput = '';
    public ?UploadedFile $company_logo = null;
    public bool $remove_logo = false;
    public ?string $companyLogoPath = null;

    public function mount(CompanySettings $settings): void
    {
        foreach (array_keys($this->rules()) as $field) {
            if (! str_ends_with($field, '.*') && $field !== 'company_logo' && $field !== 'remove_logo') {
                $this->{$field} = $settings->{$field} ?? (is_array($this->{$field}) ? [] : '');
            }
        }
        $this->atecoCodesInput = implode(', ', $this->company_ateco_codes ?? []);
        $this->companyLogoPath = $settings->company_logo_path;
    }

    public function save(CompanySettings $settings): void
    {
        $this->ensureAllowed();
        $this->company_ateco_codes = collect(explode(',', $this->atecoCodesInput))->map(fn (string $code) => trim($code))->filter()->values()->all();
        $validated = $this->validate();
        if ($this->remove_logo && $settings->company_logo_path) {
            \Storage::disk('public')->delete($settings->company_logo_path);
            $settings->company_logo_path = null;
        }
        if ($this->company_logo) {
            if ($settings->company_logo_path) \Storage::disk('public')->delete($settings->company_logo_path);
            $settings->company_logo_path = $this->company_logo->storeAs('logos', 'company-logo.'.$this->company_logo->getClientOriginalExtension(), 'public');
        }
        $oldRegime = $settings->company_fiscal_regime;
        $oldRf19 = $settings->rf19_self_invoices_enabled;
        unset($validated['company_logo'], $validated['remove_logo']);
        $validated['company_vat_number'] = ItalianVatNumber::normalize($validated['company_vat_number'] ?: null) ?? '';
        $settings->fill($validated);
        $settings->save();
        $this->companyLogoPath = $settings->company_logo_path;
        $this->company_logo = null;
        $this->remove_logo = false;
        if ($oldRegime !== $settings->company_fiscal_regime || $oldRf19 !== $settings->rf19_self_invoices_enabled) Log::info('Fiscal regime settings updated', ['user_id' => request()->user()?->id, 'old_regime' => $oldRegime, 'new_regime' => $settings->company_fiscal_regime, 'old_rf19_self_invoices_enabled' => $oldRf19, 'new_rf19_self_invoices_enabled' => $settings->rf19_self_invoices_enabled]);
        session()->flash('success', 'Impostazioni salvate.');
    }

    protected function rules(): array
    {
        return ['company_name' => 'required|string', 'company_vat_number' => ['nullable', new ItalianVatNumber], 'company_tax_code' => 'nullable|string', 'company_address' => 'nullable|string', 'company_postal_code' => 'nullable|string', 'company_city' => 'nullable|string', 'company_province' => 'nullable|string', 'company_country' => 'required|size:2', 'company_email' => 'nullable|email', 'company_pec' => 'nullable|string', 'company_sdi_code' => 'nullable|string', 'company_fiscal_regime' => ['required', Rule::in(array_column(FiscalRegime::options(), 'value'))], 'rf19_self_invoices_enabled' => 'boolean', 'company_ateco_codes' => 'nullable|array', 'company_ateco_codes.*' => 'string', 'company_logo' => 'nullable|image|max:1024', 'remove_logo' => 'boolean'];
    }

    public function fiscalRegimes(): array { return FiscalRegime::options(); }
    public function countries(): array { return [['value' => 'IT', 'label' => 'Italia'], ['value' => 'AT', 'label' => 'Austria'], ['value' => 'BE', 'label' => 'Belgio'], ['value' => 'BG', 'label' => 'Bulgaria'], ['value' => 'CY', 'label' => 'Cipro'], ['value' => 'HR', 'label' => 'Croazia'], ['value' => 'DK', 'label' => 'Danimarca'], ['value' => 'EE', 'label' => 'Estonia'], ['value' => 'FI', 'label' => 'Finlandia'], ['value' => 'FR', 'label' => 'Francia'], ['value' => 'DE', 'label' => 'Germania'], ['value' => 'GR', 'label' => 'Grecia'], ['value' => 'IE', 'label' => 'Irlanda'], ['value' => 'LV', 'label' => 'Lettonia'], ['value' => 'LT', 'label' => 'Lituania'], ['value' => 'LU', 'label' => 'Lussemburgo'], ['value' => 'MT', 'label' => 'Malta'], ['value' => 'NL', 'label' => 'Paesi Bassi'], ['value' => 'PL', 'label' => 'Polonia'], ['value' => 'PT', 'label' => 'Portogallo'], ['value' => 'CZ', 'label' => 'Repubblica Ceca'], ['value' => 'RO', 'label' => 'Romania'], ['value' => 'SK', 'label' => 'Slovacchia'], ['value' => 'SI', 'label' => 'Slovenia'], ['value' => 'ES', 'label' => 'Spagna'], ['value' => 'SE', 'label' => 'Svezia'], ['value' => 'HU', 'label' => 'Ungheria'], ['value' => 'CH', 'label' => 'Svizzera'], ['value' => 'GB', 'label' => 'Regno Unito'], ['value' => 'US', 'label' => 'Stati Uniti'], ['value' => 'CN', 'label' => 'Cina']]; }
    public function atecoLabel(string $code): string { return AtecoCode::label($code); }
    private function ensureAllowed(): void { abort_unless(app(EnvironmentCapabilities::class)->can('edit-company-settings'), 403, 'Operazione non consentita in questa modalità.'); }
}; ?>

<x-slot:header><div><p class="text-xs font-bold uppercase tracking-[.12em] text-content-muted">Configurazione</p><h1 class="text-lg font-bold text-content">Dati azienda</h1></div></x-slot:header>
<section class="space-y-6"><form wire:submit="save" class="grid gap-6 lg:grid-cols-2">@if(session('success'))<div class="lg:col-span-2 rounded-md border border-success/20 bg-success-bg p-4 text-sm text-success">{{ session('success') }}</div>@endif
    <article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]"><h2 class="font-bold">Informazioni generali</h2><div class="mt-4 space-y-4"><x-settings.input wire:model="company_name" label="Nome azienda *"/><x-settings.input wire:model="company_vat_number" label="Partita IVA"/><x-settings.input wire:model="company_tax_code" label="Codice fiscale"/></div></article>
    <article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]"><h2 class="font-bold">Indirizzo</h2><div class="mt-4 grid gap-4 sm:grid-cols-2"><div class="sm:col-span-2"><x-settings.input wire:model="company_address" label="Via"/></div><x-settings.input wire:model="company_postal_code" label="CAP"/><x-settings.input wire:model="company_city" label="Città"/><x-settings.input wire:model="company_province" label="Provincia" maxlength="2"/><label class="block text-sm font-semibold">Paese *<select wire:model="company_country" class="mt-2 block w-full rounded-md border border-border px-3 py-2">@foreach($this->countries() as $country)<option value="{{ $country['value'] }}">{{ $country['label'] }}</option>@endforeach</select></label></div></article>
    <article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]"><h2 class="font-bold">Fatturazione elettronica</h2><div class="mt-4 space-y-4"><label class="block text-sm font-semibold">Regime fiscale *<select wire:model.live="company_fiscal_regime" class="mt-2 block w-full rounded-md border border-border px-3 py-2">@foreach($this->fiscalRegimes() as $regime)<option value="{{ $regime['value'] }}">{{ $regime['label'] }}</option>@endforeach</select></label><x-settings.input wire:model="company_email" type="email" label="Email"/><x-settings.input wire:model="company_pec" label="PEC"/><x-settings.input wire:model="company_sdi_code" label="Codice SDI" maxlength="7"/>@if($company_fiscal_regime === 'RF19')<label class="flex gap-2 text-sm"><input wire:model="rf19_self_invoices_enabled" type="checkbox"> Abilita autofatture RF19 (solo estero)</label>@endif</div></article>
    <article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]"><h2 class="font-bold">Codici ATECO e logo</h2><div class="mt-4 space-y-4"><label class="block text-sm font-semibold">Codici ATECO (separati da virgola)<input wire:model="atecoCodesInput" class="mt-2 block w-full rounded-md border border-border px-3 py-2" type="text"></label><p class="text-xs text-content-muted">{{ implode(', ', array_map(fn ($code) => $code.' - '.$this->atecoLabel($code), $company_ateco_codes)) }}</p>@if($companyLogoPath && ! $remove_logo)<img src="{{ asset('storage/'.$companyLogoPath) }}" alt="Logo azienda" class="h-16 rounded border p-2">@endif<label class="block text-sm font-semibold">Logo<input wire:model="company_logo" class="mt-2 block w-full text-sm" type="file" accept="image/*"></label>@if($companyLogoPath)<label class="flex gap-2 text-sm"><input wire:model="remove_logo" type="checkbox"> Rimuovi logo</label>@endif</div></article>
    <div class="lg:col-span-2">@if(app(EnvironmentCapabilities::class)->can('edit-company-settings'))<button class="rounded-md bg-primary px-5 py-2.5 text-sm font-bold text-white" type="submit">Salva impostazioni</button>@else<p class="text-sm text-content-muted">Configurazione in sola lettura.</p>@endif</div>
</form></section>
