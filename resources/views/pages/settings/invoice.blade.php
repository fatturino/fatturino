<?php

use App\Contracts\EnvironmentCapabilities;
use App\Enums\FundType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentTerms;
use App\Enums\VatPayability;
use App\Enums\VatRate;
use App\Settings\CompanySettings;
use App\Settings\InvoiceSettings;
use App\Support\FiscalRegimePolicy;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component {
    public string $default_vat_rate = '';

    public bool $withholding_tax_enabled = false;

    public string $withholding_tax_percent = '20.00';

    public bool $fund_enabled = false;

    public string $fund_type = '';

    public string $fund_percent = '4.00';

    public string $fund_vat_rate = '';

    public bool $fund_has_deduction = false;

    public bool $auto_stamp_duty = false;

    public string $stamp_duty_threshold = '77.47';

    public string $default_payment_method = '';

    public string $default_payment_terms = '';

    public string $default_bank_name = '';

    public string $default_bank_iban = '';

    public string $default_vat_payability = 'I';

    public bool $default_split_payment = false;

    public string $default_notes = '';

    public string $fiscalRegime = 'RF01';

    public function mount(InvoiceSettings $settings, CompanySettings $company): void
    {
        foreach (array_keys($this->rules()) as $field) {
            $value = $settings->{$field} ?? null;
            $this->{$field} = $value instanceof BackedEnum ? $value->value : ($value ?? (is_bool($this->{$field}) ? false : ''));
        }
        $this->fiscalRegime = $company->company_fiscal_regime;
        if ($this->isRf19()) {
            $this->applyRf19Restrictions();
        }
    }

    public function save(InvoiceSettings $settings): void
    {
        $this->ensureAllowed();
        if ($this->isRf19()) {
            $this->applyRf19Restrictions();
        }
        $payload = FiscalRegimePolicy::normalizeInvoiceSettingsPayload($this->validate(), $this->fiscalRegime);
        $payload['default_vat_rate'] = filled($payload['default_vat_rate']) ? VatRate::from($payload['default_vat_rate']) : null;
        $payload['fund_vat_rate'] = filled($payload['fund_vat_rate']) ? VatRate::from($payload['fund_vat_rate']) : null;
        $settings->fill($payload);
        $settings->save();
        session()->flash('success', 'Impostazioni fatture salvate.');
    }

    protected function rules(): array
    {
        return ['default_vat_rate' => ['nullable', Rule::in(array_column(VatRate::options(), 'id'))], 'withholding_tax_enabled' => 'boolean', 'withholding_tax_percent' => 'nullable|string', 'fund_enabled' => 'boolean', 'fund_type' => 'nullable|string', 'fund_percent' => 'nullable|string', 'fund_vat_rate' => ['nullable', Rule::in(array_column(VatRate::options(), 'id'))], 'fund_has_deduction' => 'boolean', 'auto_stamp_duty' => 'boolean', 'stamp_duty_threshold' => 'nullable|string', 'default_payment_method' => 'nullable|string', 'default_payment_terms' => 'nullable|string', 'default_bank_name' => 'nullable|string', 'default_bank_iban' => 'nullable|string', 'default_vat_payability' => ['nullable', Rule::in(array_column(VatPayability::options(), 'id'))], 'default_split_payment' => 'boolean', 'default_notes' => 'nullable|string'];
    }

    public function vatRates(): array
    {
        return $this->isRf19() ? array_values(array_filter(VatRate::options(), fn (array $rate) => $rate['id'] === FiscalRegimePolicy::FORFETTARIO_VAT_RATE)) : VatRate::options();
    }

    public function isRf19(): bool
    {
        return $this->fiscalRegime === 'RF19';
    }

    public function paymentMethods(): array
    {
        return PaymentMethod::options();
    }

    public function paymentTerms(): array
    {
        return PaymentTerms::options();
    }

    public function fundTypes(): array
    {
        return FundType::options();
    }

    public function vatPayabilityOptions(): array
    {
        return VatPayability::options();
    }

    private function applyRf19Restrictions(): void
    {
        $this->withholding_tax_enabled = false;
        $this->default_split_payment = false;
        $this->default_vat_payability = 'I';
    }

    private function ensureAllowed(): void
    {
        abort_unless(app(EnvironmentCapabilities::class)->can('edit-invoice-settings'), 403, 'Operazione non consentita in questa modalità.');
    }
};
?>

<x-slot:header><div><p class="text-xs font-bold uppercase tracking-[.12em] text-content-muted">Configurazione</p><h1 class="text-lg font-bold text-content">Impostazioni fatture</h1></div></x-slot:header>

@php($canEdit = app(EnvironmentCapabilities::class)->can('edit-invoice-settings'))

<section class="space-y-6">
    <form wire:submit="save" class="max-w-6xl space-y-6">
        @if(session('success'))
            <div class="rounded-lg border border-success/20 bg-success-bg p-4 text-sm font-medium text-success" role="status">{{ session('success') }}</div>
        @endif

        <fieldset @disabled(! $canEdit) class="grid gap-6 lg:grid-cols-2" @class(['select-none opacity-70' => ! $canEdit])>
            <article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)] sm:p-6">
                <h2 class="text-base font-bold text-content">Predefiniti</h2>
                <div class="mt-5 space-y-4">
                    <x-select label="Aliquota IVA" wire:model="default_vat_rate" :disabled="! $canEdit" :options="$this->vatRates()" />
                </div>
            </article>

            @if(! $this->isRf19())
                <article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)] sm:p-6">
                    <h2 class="text-base font-bold text-content">Ritenuta d'acconto</h2>
                    <div class="mt-5 space-y-4">
                        <x-toggle wire:model.live="withholding_tax_enabled" :disabled="! $canEdit" label="Abilita ritenuta" />
                        @if($withholding_tax_enabled)
                            <x-settings.input wire:model="withholding_tax_percent" :disabled="! $canEdit" type="number" label="Percentuale" />
                        @endif
                    </div>
                </article>
            @endif

            <article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)] sm:p-6">
                <h2 class="text-base font-bold text-content">Cassa previdenziale</h2>
                <div class="mt-5 space-y-4">
                    <x-toggle wire:model.live="fund_enabled" :disabled="! $canEdit" label="Abilita cassa" />
                    @if($fund_enabled)
                        <div class="space-y-4 border-t border-border-light pt-4">
                            <x-select label="Tipo" wire:model="fund_type" :disabled="! $canEdit" :options="$this->fundTypes()" />
                            <x-settings.input wire:model="fund_percent" :disabled="! $canEdit" type="number" label="Percentuale" />
                            <x-select label="IVA rivalsa" wire:model="fund_vat_rate" :disabled="! $canEdit" :options="$this->vatRates()" />
                            <x-toggle wire:model="fund_has_deduction" :disabled="! $canEdit" label="Rivalsa con deduzione" />
                        </div>
                    @endif
                </div>
            </article>

            <article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)] sm:p-6">
                <h2 class="text-base font-bold text-content">Bollo virtuale</h2>
                <div class="mt-5 space-y-4">
                    <x-toggle wire:model.live="auto_stamp_duty" :disabled="! $canEdit" label="Applica automaticamente (€2,00)" />
                    @if($auto_stamp_duty)
                        <div class="border-t border-border-light pt-4">
                            <x-settings.input wire:model="stamp_duty_threshold" :disabled="! $canEdit" type="number" label="Soglia imponibile (€)" />
                        </div>
                    @endif
                </div>
            </article>

            <article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)] sm:p-6">
                <h2 class="text-base font-bold text-content">Pagamenti</h2>
                <div class="mt-5 space-y-4">
                    <x-select label="Metodo" wire:model="default_payment_method" :disabled="! $canEdit" :options="$this->paymentMethods()" />
                    <x-select label="Termini" wire:model="default_payment_terms" :disabled="! $canEdit" :options="$this->paymentTerms()" />
                    <x-settings.input wire:model="default_bank_name" :disabled="! $canEdit" label="Banca" />
                    <x-settings.input wire:model="default_bank_iban" :disabled="! $canEdit" label="IBAN" />
                </div>
            </article>

            <article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)] sm:p-6">
                <h2 class="text-base font-bold text-content">IVA e note</h2>
                <div class="mt-5 space-y-4">
                    <x-select label="Esigibilità" wire:model="default_vat_payability" :disabled="$this->isRf19() || ! $canEdit" :options="$this->vatPayabilityOptions()" />
                    @if(! $this->isRf19())
                        <x-toggle wire:model="default_split_payment" :disabled="! $canEdit" label="Split payment predefinito" />
                    @endif
                    <label class="block text-sm font-medium text-content">
                        Note
                        <textarea wire:model="default_notes" @disabled(! $canEdit) class="mt-1.5 block min-h-28 w-full rounded-lg border border-border-strong bg-white px-3 py-2.5 text-sm leading-6 text-content placeholder:text-text-muted focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:bg-surface-muted disabled:text-content-muted" rows="3"></textarea>
                        @error('default_notes')<span class="mt-1 block text-xs text-danger">{{ $message }}</span>@enderror
                    </label>
                </div>
            </article>
        </fieldset>

        <div class="flex flex-col-reverse gap-3 border-t border-border-light pt-5 sm:flex-row sm:items-center">
            @if($canEdit)
                <button wire:loading.attr="disabled" wire:target="save" class="inline-flex h-11 items-center justify-center rounded-lg bg-primary px-5 text-sm font-bold text-white transition hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-60" type="submit">
                    <span wire:loading.remove wire:target="save">Salva impostazioni</span>
                    <span wire:loading wire:target="save" role="status">Salvataggio in corso...</span>
                </button>
            @else
                <p class="text-sm text-content-muted">Configurazione in sola lettura.</p>
            @endif
        </div>
    </form>
</section>
