<?php

use App\Contracts\EnvironmentCapabilities;
use App\Enums\FundType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentTerms;
use App\Enums\VatPayability;
use App\Enums\VatRate;
use App\Models\Sequence;
use App\Settings\CompanySettings;
use App\Settings\InvoiceSettings;
use App\Support\FiscalRegimePolicy;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component {
    public ?int $default_sequence_sales = null;
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
            if ($field === 'default_sequence_sales') {
                $this->default_sequence_sales = filled($value) ? (int) $value : null;

                continue;
            }
            $this->{$field} = $value instanceof \BackedEnum ? $value->value : ($value ?? (is_bool($this->{$field}) ? false : ''));
        }
        $this->fiscalRegime = $company->company_fiscal_regime;
        if ($this->isRf19()) $this->applyRf19Restrictions();
    }

    public function save(InvoiceSettings $settings): void
    {
        $this->ensureAllowed();
        if ($this->isRf19()) $this->applyRf19Restrictions();
        $payload = FiscalRegimePolicy::normalizeInvoiceSettingsPayload($this->validate(), $this->fiscalRegime);
        $payload['default_vat_rate'] = filled($payload['default_vat_rate']) ? VatRate::from($payload['default_vat_rate']) : null;
        $payload['fund_vat_rate'] = filled($payload['fund_vat_rate']) ? VatRate::from($payload['fund_vat_rate']) : null;
        $settings->fill($payload);
        $settings->save();
        session()->flash('success', 'Impostazioni fatture salvate.');
    }

    protected function rules(): array
    {
        return ['default_sequence_sales' => 'nullable|exists:sequences,id', 'default_vat_rate' => ['nullable', Rule::in(array_column(VatRate::options(), 'id'))], 'withholding_tax_enabled' => 'boolean', 'withholding_tax_percent' => 'nullable|string', 'fund_enabled' => 'boolean', 'fund_type' => 'nullable|string', 'fund_percent' => 'nullable|string', 'fund_vat_rate' => ['nullable', Rule::in(array_column(VatRate::options(), 'id'))], 'fund_has_deduction' => 'boolean', 'auto_stamp_duty' => 'boolean', 'stamp_duty_threshold' => 'nullable|string', 'default_payment_method' => 'nullable|string', 'default_payment_terms' => 'nullable|string', 'default_bank_name' => 'nullable|string', 'default_bank_iban' => 'nullable|string', 'default_vat_payability' => ['nullable', Rule::in(array_column(VatPayability::options(), 'id'))], 'default_split_payment' => 'boolean', 'default_notes' => 'nullable|string'];
    }

    public function vatRates(): array { return $this->isRf19() ? array_values(array_filter(VatRate::options(), fn (array $rate) => $rate['id'] === FiscalRegimePolicy::FORFETTARIO_VAT_RATE)) : VatRate::options(); }
    public function isRf19(): bool { return $this->fiscalRegime === 'RF19'; }
    public function paymentMethods(): array { return PaymentMethod::options(); }
    public function paymentTerms(): array { return PaymentTerms::options(); }
    public function fundTypes(): array { return FundType::options(); }
    public function vatPayabilityOptions(): array { return VatPayability::options(); }
    private function applyRf19Restrictions(): void { $this->withholding_tax_enabled = false; $this->default_split_payment = false; $this->default_vat_payability = 'I'; }
    private function ensureAllowed(): void { abort_unless(app(EnvironmentCapabilities::class)->can('edit-invoice-settings'), 403, 'Operazione non consentita in questa modalità.'); }
}; ?>

<x-slot:header><div><p class="text-xs font-bold uppercase tracking-[.12em] text-content-muted">Configurazione</p><h1 class="text-lg font-bold text-content">Impostazioni fatture</h1></div></x-slot:header>
<form wire:submit="save" class="space-y-6">@if(session('success'))<div class="rounded-md border border-success/20 bg-success-bg p-4 text-sm text-success">{{ session('success') }}</div>@endif
<div class="grid gap-6 lg:grid-cols-2">
<article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]"><h2 class="font-bold">Predefiniti</h2><div class="mt-4 space-y-4"><label class="block text-sm font-semibold">Sequenza vendite<select wire:model="default_sequence_sales" class="mt-2 block w-full rounded-md border border-border px-3 py-2"><option value="">—</option>@foreach(Sequence::where('type', 'sales')->orderBy('name')->get() as $sequence)<option value="{{ $sequence->id }}">{{ $sequence->name }}</option>@endforeach</select></label><label class="block text-sm font-semibold">Aliquota IVA<select wire:model="default_vat_rate" class="mt-2 block w-full rounded-md border border-border px-3 py-2"><option value="">—</option>@foreach($this->vatRates() as $rate)<option value="{{ $rate['id'] }}">{{ $rate['name'] }}</option>@endforeach</select></label></div></article>
@if(! $this->isRf19())<article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]"><h2 class="font-bold">Ritenuta d'acconto</h2><label class="mt-4 flex gap-2 text-sm"><input wire:model="withholding_tax_enabled" type="checkbox"> Abilita ritenuta</label>@if($withholding_tax_enabled)<x-settings.input wire:model="withholding_tax_percent" class="mt-4" type="number" label="Percentuale"/>@endif</article>@endif
<article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]"><h2 class="font-bold">Cassa previdenziale</h2><label class="mt-4 flex gap-2 text-sm"><input wire:model="fund_enabled" type="checkbox"> Abilita cassa</label>@if($fund_enabled)<div class="mt-4 space-y-4"><label class="block text-sm font-semibold">Tipo<select wire:model="fund_type" class="mt-2 block w-full rounded-md border border-border px-3 py-2"><option value="">—</option>@foreach($this->fundTypes() as $type)<option value="{{ $type['id'] }}">{{ $type['name'] }}</option>@endforeach</select></label><x-settings.input wire:model="fund_percent" type="number" label="Percentuale"/><label class="block text-sm font-semibold">IVA rivalsa<select wire:model="fund_vat_rate" class="mt-2 block w-full rounded-md border border-border px-3 py-2"><option value="">—</option>@foreach($this->vatRates() as $rate)<option value="{{ $rate['id'] }}">{{ $rate['name'] }}</option>@endforeach</select></label><label class="flex gap-2 text-sm"><input wire:model="fund_has_deduction" type="checkbox"> Rivalsa con deduzione</label></div>@endif</article>
<article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]"><h2 class="font-bold">Bollo virtuale</h2><label class="mt-4 flex gap-2 text-sm"><input wire:model="auto_stamp_duty" type="checkbox"> Applica automaticamente (€2,00)</label>@if($auto_stamp_duty)<x-settings.input wire:model="stamp_duty_threshold" class="mt-4" type="number" label="Soglia imponibile (€)"/>@endif</article>
<article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]"><h2 class="font-bold">Pagamenti</h2><div class="mt-4 space-y-4"><label class="block text-sm font-semibold">Metodo<select wire:model="default_payment_method" class="mt-2 block w-full rounded-md border border-border px-3 py-2"><option value="">—</option>@foreach($this->paymentMethods() as $method)<option value="{{ $method['id'] }}">{{ $method['name'] }}</option>@endforeach</select></label><label class="block text-sm font-semibold">Termini<select wire:model="default_payment_terms" class="mt-2 block w-full rounded-md border border-border px-3 py-2"><option value="">—</option>@foreach($this->paymentTerms() as $term)<option value="{{ $term['id'] }}">{{ $term['name'] }}</option>@endforeach</select></label><x-settings.input wire:model="default_bank_name" label="Banca"/><x-settings.input wire:model="default_bank_iban" label="IBAN"/></div></article>
<article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]"><h2 class="font-bold">IVA e note</h2><div class="mt-4 space-y-4"><label class="block text-sm font-semibold">Esigibilità<select wire:model="default_vat_payability" @disabled($this->isRf19()) class="mt-2 block w-full rounded-md border border-border px-3 py-2">@foreach($this->vatPayabilityOptions() as $option)<option value="{{ $option['id'] }}">{{ $option['name'] }}</option>@endforeach</select></label>@if(! $this->isRf19())<label class="flex gap-2 text-sm"><input wire:model="default_split_payment" type="checkbox"> Split payment predefinito</label>@endif<label class="block text-sm font-semibold">Note<textarea wire:model="default_notes" class="mt-2 block w-full rounded-md border border-border px-3 py-2" rows="3"></textarea></label></div></article></div>
@if(app(EnvironmentCapabilities::class)->can('edit-invoice-settings'))<button class="rounded-md bg-primary px-5 py-2.5 text-sm font-bold text-white" type="submit">Salva impostazioni</button>@else<p class="text-sm text-content-muted">Configurazione in sola lettura.</p>@endif</form>
