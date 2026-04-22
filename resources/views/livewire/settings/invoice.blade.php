<div>
    <x-header :title="__('app.settings.invoice.title')" separator>
        <x-slot:actions>
            <x-button :label="__('app.common.save')" wire:click="save" icon="o-check" class="btn-primary" spinner="save" />
        </x-slot:actions>
    </x-header>

    <x-form wire:submit="save">
        <div class="grid lg:grid-cols-2 gap-5">
            {{-- Defaults --}}
            <x-card :title="__('app.settings.invoice.defaults_section')" separator>
                <div class="grid gap-3">
                    <x-select :label="__('app.settings.invoice.default_sequence')" :options="$sequences" wire:model="default_sequence_sales" />
                    <x-select :label="__('app.settings.invoice.default_vat_rate')" :options="$vatRates" wire:model="default_vat_rate" option-label="name" />
                </div>
            </x-card>

            {{-- Withholding Tax (Ritenuta d'acconto) --}}
            <x-card :title="__('app.settings.invoice.withholding_section')" separator>
                <div class="grid gap-3">
                    <x-checkbox :label="__('app.settings.invoice.withholding_tax_enabled')" wire:model.live="withholding_tax_enabled" />
                    <x-input :label="__('app.settings.invoice.withholding_tax_percent')" wire:model="withholding_tax_percent" type="number" step="0.01" suffix="%" />
                </div>
            </x-card>

            {{-- Professional Fund (Cassa Previdenziale) --}}
            <x-card :title="__('app.settings.invoice.fund_section')" separator>
                <div class="grid gap-3">
                    <x-checkbox :label="__('app.settings.invoice.fund_enabled')" wire:model.live="fund_enabled" />
                    @if($fund_enabled)
                        {{-- Percent + Type on same row (compact, like Fattura24) --}}
                        <div class="grid grid-cols-3 gap-2">
                            <x-input wire:model="fund_percent" type="number" step="0.01" suffix="%" />
                            <div class="col-span-2">
                                <x-select :options="\App\Enums\FundType::options()" wire:model.live="fund_type" :placeholder="__('app.common.select')" />
                            </div>
                        </div>
                        <x-select :label="__('app.settings.invoice.fund_vat_rate')" :options="$vatRates" wire:model="fund_vat_rate" option-label="name" :placeholder="__('app.common.select')" />
                        <x-checkbox :label="__('app.settings.invoice.fund_has_deduction')" wire:model="fund_has_deduction" />
                    @endif
                </div>
            </x-card>

            {{-- Stamp Duty --}}
            <x-card :title="__('app.settings.invoice.stamp_duty_section')" separator>
                <div class="grid gap-3">
                    <x-checkbox :label="__('app.settings.invoice.auto_stamp_duty')" wire:model="auto_stamp_duty" />
                    <x-input :label="__('app.settings.invoice.stamp_duty_threshold')" wire:model="stamp_duty_threshold" type="number" step="0.01" />
                </div>
            </x-card>

            {{-- Payments --}}
            <x-card :title="__('app.settings.invoice.payments_section')" separator>
                <div class="grid gap-3">
                    <x-select :label="__('app.settings.invoice.default_payment_method')" :options="\App\Enums\PaymentMethod::options()" wire:model="default_payment_method" :placeholder="__('app.common.select')" />
                    <x-select :label="__('app.settings.invoice.default_payment_terms')" :options="\App\Enums\PaymentTerms::options()" wire:model="default_payment_terms" :placeholder="__('app.common.select')" />
                    <x-input :label="__('app.settings.invoice.default_bank_name')" wire:model="default_bank_name" />
                    <x-input :label="__('app.settings.invoice.default_iban')" wire:model="default_bank_iban" />
                </div>
            </x-card>

            {{-- VAT --}}
            <x-card :title="__('app.settings.invoice.vat_section')" separator>
                <div class="grid gap-3">
                    <x-select :label="__('app.settings.invoice.default_vat_payability')" :options="\App\Enums\VatPayability::options()" wire:model="default_vat_payability" />
                    <x-checkbox :label="__('app.settings.invoice.default_split_payment')" wire:model="default_split_payment" />
                </div>
            </x-card>

            {{-- Other --}}
            <x-card :title="__('app.settings.invoice.other_section')" separator>
                <div class="grid gap-3">
                    <x-textarea :label="__('app.settings.invoice.default_notes')" wire:model="default_notes" rows="3" />
                </div>
            </x-card>
        </div>

    </x-form>
</div>
