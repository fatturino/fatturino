<?php

use App\Enums\FiscalRegime;
use App\Models\User;
use App\Rules\ItalianVatNumber;
use App\Settings\CompanySettings;
use App\Settings\InvoiceSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::guest')] #[Title('Fatturino - Configurazione')] class extends Component {
    public int $step = 1;
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $company_name = '';
    public string $company_vat_number = '';
    public string $company_tax_code = '';
    public string $company_fiscal_regime = 'RF01';
    public bool $withholding_tax_enabled = false;
    public bool $auto_stamp_duty = false;
    public string $company_address = '';
    public string $company_city = '';
    public string $company_postal_code = '';
    public string $company_province = '';
    public string $company_country = 'IT';
    public string $company_pec = '';
    public string $company_sdi_code = '0000000';

    public function mount(CompanySettings $settings): void
    {
        if (User::query()->exists()) { $this->redirectRoute('login', navigate: false); return; }
        $this->step = min(3, max(1, (int) session('setup_step', 1)));
        foreach (array_merge([
            'company_name' => $settings->company_name, 'company_vat_number' => $settings->company_vat_number,
            'company_tax_code' => $settings->company_tax_code, 'company_fiscal_regime' => $settings->company_fiscal_regime ?: 'RF01',
            'company_address' => $settings->company_address, 'company_city' => $settings->company_city,
            'company_postal_code' => $settings->company_postal_code, 'company_province' => $settings->company_province,
            'company_country' => $settings->company_country ?: 'IT', 'company_pec' => $settings->company_pec,
            'company_sdi_code' => $settings->company_sdi_code ?: '0000000',
        ], session('setup_data', [])) as $field => $value) { if (property_exists($this, $field)) $this->{$field} = (string) $value; }
        $this->applyFiscalRegimeDefaults();
    }

    public function updatedName(): void { if ($this->company_name === '') $this->company_name = $this->name; }
    public function updatedCompanyFiscalRegime(): void { $this->applyFiscalRegimeDefaults(); }
    public function previous(): void { if ($this->step > 1) { $this->step--; $this->resetValidation(); } }

    public function next(CompanySettings $companySettings, InvoiceSettings $invoiceSettings): void
    {
        $rules = match ($this->step) {
            1 => ['name' => ['required', 'min:2'], 'email' => ['required', 'email', 'unique:users,email'], 'password' => ['required', 'min:8', 'confirmed'], 'password_confirmation' => ['required']],
            2 => ['company_name' => ['required', 'min:2'], 'company_vat_number' => ['required', new ItalianVatNumber], 'company_tax_code' => ['required'], 'company_fiscal_regime' => ['required', 'in:RF01,RF19']],
            3 => ['company_address' => ['required'], 'company_postal_code' => ['required'], 'company_city' => ['required'], 'company_province' => ['required', 'size:2'], 'company_country' => ['required', 'size:2'], 'company_pec' => ['required', 'email'], 'company_sdi_code' => ['required', 'size:7']],
        };
        $this->validate($rules);
        $this->persistStep();
        if ($this->step < 3) { session(['setup_step' => ++$this->step]); return; }
        $user = User::create(['name' => $this->name, 'email' => $this->email, 'password' => Hash::make($this->password), 'is_admin' => true]);
        $companySettings->company_name = $this->company_name;
        $companySettings->company_vat_number = ItalianVatNumber::normalize($this->company_vat_number) ?? '';
        foreach (['company_tax_code', 'company_fiscal_regime', 'company_address', 'company_city', 'company_postal_code', 'company_province', 'company_country', 'company_pec', 'company_sdi_code'] as $field) $companySettings->{$field} = $this->{$field};
        $companySettings->rf19_self_invoices_enabled = false; $companySettings->save();
        $invoiceSettings->withholding_tax_enabled = $this->withholding_tax_enabled; $invoiceSettings->auto_stamp_duty = $this->auto_stamp_duty; $invoiceSettings->save();
        Auth::login($user); session()->regenerate(); session()->forget(['setup_step', 'setup_data']);
        $this->redirectRoute('dashboard', navigate: false);
    }

    private function persistStep(): void { $data = session('setup_data', []); foreach (match ($this->step) { 1 => ['name','email'], 2 => ['company_name','company_vat_number','company_tax_code','company_fiscal_regime','withholding_tax_enabled','auto_stamp_duty'], 3 => ['company_address','company_city','company_postal_code','company_province','company_country','company_pec','company_sdi_code'] } as $field) $data[$field] = $field === 'company_vat_number' ? ItalianVatNumber::normalize($this->{$field}) : $this->{$field}; session(['setup_data' => $data]); }
    private function applyFiscalRegimeDefaults(): void { if ($this->company_fiscal_regime === FiscalRegime::RF19->value) { $this->auto_stamp_duty = true; $this->withholding_tax_enabled = false; } else { $this->auto_stamp_duty = false; $this->withholding_tax_enabled = true; } }
}; ?>

<main class="min-h-dvh bg-canvas p-4 lg:p-8"><div class="mx-auto grid max-w-5xl overflow-hidden border border-border-light bg-white shadow-[var(--shadow-elevated)] lg:grid-cols-[.7fr_1.3fr]">
    <aside class="bg-[linear-gradient(145deg,var(--color-ink),var(--color-indigo))] p-8 text-white lg:p-12"><img src="{{ asset('brand/logo-white.svg') }}" alt="Fatturino" class="h-9"><p class="mt-12 text-xs font-bold tracking-[.14em] text-aqua">PRIMA CONFIGURAZIONE</p><h1 class="mt-3 text-3xl font-bold">Pronti a partire.</h1><p class="mt-4 leading-6 text-white/75">Tre passaggi guidati per configurare account, azienda e fatturazione elettronica.</p></aside>
    <section class="p-7 sm:p-10 lg:p-12"><div class="mb-8 flex gap-2">@foreach (['Account','Azienda','Fatturazione'] as $index => $label)<div class="flex-1 border-t-2 pt-2 text-xs font-bold {{ $step >= $index + 1 ? 'border-primary text-primary' : 'border-border text-content-muted' }}">{{ $index + 1 }}. {{ $label }}</div>@endforeach</div>
        <form wire:submit="next" class="space-y-5"><p class="text-sm text-content-muted">I campi contrassegnati con * sono obbligatori.</p>
            @if($step === 1)<x-setup.field label="Nome *" field="name" autocomplete="name" /><x-setup.field label="Email *" field="email" type="email" autocomplete="email" /><x-setup.field label="Password *" field="password" type="password" autocomplete="new-password" /><x-setup.field label="Conferma password *" field="password_confirmation" type="password" autocomplete="new-password" />
            @elseif($step === 2)<x-setup.field label="Nome azienda *" field="company_name" autocomplete="organization" /><div class="grid gap-5 sm:grid-cols-2"><x-setup.field label="Partita IVA *" field="company_vat_number" /><x-setup.field label="Codice fiscale *" field="company_tax_code" /></div><label class="block text-sm font-semibold text-content">Regime fiscale *<select wire:model.live="company_fiscal_regime" class="mt-2 block w-full rounded-md border border-border px-4 py-3"><option value="RF01">{{ FiscalRegime::RF01->label() }}</option><option value="RF19">{{ FiscalRegime::RF19->label() }}</option></select></label>
            @else<x-setup.field label="Indirizzo *" field="company_address" autocomplete="street-address" /><div class="grid gap-5 sm:grid-cols-3"><x-setup.field label="CAP *" field="company_postal_code" /><x-setup.field label="Città *" field="company_city" /><x-setup.field label="Provincia *" field="company_province" maxlength="2" /></div><x-setup.field label="Nazione *" field="company_country" maxlength="2" /><x-setup.field label="PEC *" field="company_pec" type="email" /><x-setup.field label="Codice SDI *" field="company_sdi_code" maxlength="7" /><div class="space-y-3 border border-border-light p-4"><label class="flex cursor-pointer gap-2"><input wire:model="withholding_tax_enabled" type="checkbox"> Ritenuta d'acconto</label><label class="flex cursor-pointer gap-2"><input wire:model="auto_stamp_duty" type="checkbox"> Marca da bollo automatica</label></div>@endif
            <div class="flex justify-between pt-4">@if($step > 1)<button wire:click="previous" type="button" class="cursor-pointer rounded-md border border-border px-4 py-2 text-sm font-semibold">Indietro</button>@else<div></div>@endif<button type="submit" wire:loading.attr="disabled" class="cursor-pointer rounded-md bg-primary px-5 py-2.5 text-sm font-bold text-white disabled:cursor-not-allowed">{{ $step < 3 ? 'Avanti' : 'Completa setup' }}</button></div>
        </form>
    </section>
</div></main>
