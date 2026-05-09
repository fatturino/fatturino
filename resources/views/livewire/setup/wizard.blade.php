<div class="w-full max-w-xl mx-auto">
    {{-- Custom progress indicator --}}
    <div class="flex items-center gap-0 mb-8">
        @for($i = 1; $i <= 3; $i++)
            {{-- Step circle --}}
            <div class="relative flex items-center justify-center w-10 h-10 rounded-full transition-all duration-300
                {{ $step >= $i ? 'bg-primary text-primary-content shadow-lg shadow-primary/25' : 'bg-base-300 text-base-content/40' }}
                {{ $step === $i ? 'scale-110' : '' }}">
                @if($step > $i)
                    <x-icon name="o-check" class="w-5 h-5" />
                @else
                    <span class="text-sm font-bold">{{ $i }}</span>
                @endif
            </div>
            {{-- Connector line --}}
            @if($i < 3)
                <div class="flex-1 h-1 mx-2 rounded-full overflow-hidden bg-base-300">
                    <div class="h-full rounded-full wizard-connector-fill transition-all duration-500 ease-out
                        {{ $step > $i ? 'w-full' : 'w-0' }}">
                    </div>
                </div>
            @endif
        @endfor
    </div>

    {{-- Step heading with context --}}
    <div class="mb-6 guest-fade-in" wire:key="step-heading-{{ $step }}">
        <div class="w-10 h-1 rounded-full bg-accent mb-4"></div>
        <h2 class="text-2xl font-bold text-base-content tracking-tight">
            @if($step === 1) {{ __('app.setup.step_account') }}
            @elseif($step === 2) {{ __('app.setup.step_company') }}
            @else {{ __('app.setup.step_address') }}
            @endif
        </h2>
        <p class="text-base-content/50 text-sm mt-1">
            @if($step === 1) {{ __('app.setup.step_account_desc') }}
            @elseif($step === 2) {{ __('app.setup.step_company_desc') }}
            @else {{ __('app.setup.step_address_desc') }}
            @endif
        </p>
    </div>

    {{-- ── Step 1: Account ── --}}
    @if($step === 1)
        <div class="guest-fade-in" wire:key="step-form-1">
            <x-form wire:submit="nextStep">
                <div class="grid gap-3">
                    <x-input
                        wire:model="name"
                        label="{{ __('app.setup.account_name') }}"
                        icon="o-user"
                        autofocus
                        autocomplete="name"
                    />
                    <x-input
                        wire:model="email"
                        label="{{ __('app.setup.account_email') }}"
                        type="email"
                        icon="o-envelope"
                        autocomplete="email"
                    />
                    <x-input
                        wire:model="password"
                        label="{{ __('app.setup.account_password') }}"
                        type="password"
                        icon="o-lock-closed"
                        autocomplete="new-password"
                    />
                    <x-input
                        wire:model="password_confirmation"
                        label="{{ __('app.setup.account_password_confirm') }}"
                        type="password"
                        icon="o-lock-closed"
                        autocomplete="new-password"
                    />
                </div>

                <x-slot:actions>
                    <x-button
                        label="{{ __('app.setup.next') }}"
                        type="submit"
                        icon-right="o-arrow-right"
                        variant="primary"
                        spinner="nextStep"
                    />
                </x-slot:actions>
            </x-form>
        </div>
    @endif

    {{-- ── Step 2: Company Info ── --}}
    @if($step === 2)
        <div class="guest-fade-in" wire:key="step-form-2">
            <x-form wire:submit="nextStep">
                <div class="grid gap-3">
                    <x-input
                        wire:model="company_name"
                        label="{{ __('app.settings.company.company_name') }}"
                        icon="o-building-office"
                        autofocus
                    />
                    <x-input
                        wire:model="company_vat_number"
                        label="{{ __('app.settings.company.vat_number') }}"
                        icon="o-identification"
                        placeholder="IT12345678903"
                    />
                    <x-input
                        wire:model="company_tax_code"
                        label="{{ __('app.settings.company.tax_code') }}"
                        icon="o-identification"
                    />
                    <x-select
                        wire:model="company_fiscal_regime"
                        label="{{ __('app.setup.fiscal_regime') }}"
                        :options="$fiscalRegimes"
                        option-value="id"
                        option-label="name"
                        icon="o-scale"
                    />

                    <hr class="border-base-300 my-2" />
                    <div class="text-sm text-accent font-medium">{{ __('app.setup.invoice_defaults') }}</div>

                    <x-toggle
                        wire:model="auto_stamp_duty"
                        :label="__('app.setup.auto_stamp_duty')"
                        :hint="__('app.setup.auto_stamp_duty_hint')"
                    />
                    <x-toggle
                        wire:model="withholding_tax_enabled"
                        :label="__('app.setup.withholding_tax_enabled')"
                        :hint="__('app.setup.withholding_tax_hint')"
                    />
                </div>

                <x-slot:actions>
                    <div class="flex justify-between items-center w-full">
                        <x-button
                            label="{{ __('app.setup.back') }}"
                            wire:click="previousStep"
                            icon="o-arrow-left"
                            variant="ghost"
                        />
                        <x-button
                            label="{{ __('app.setup.next') }}"
                            type="submit"
                            icon-right="o-arrow-right"
                            variant="primary"
                            spinner="nextStep"
                        />
                    </div>
                </x-slot:actions>
            </x-form>
        </div>
    @endif

    {{-- ── Step 3: Address & Electronic Invoicing ── --}}
    @if($step === 3)
        <div class="guest-fade-in" wire:key="step-form-3">
            <x-form wire:submit="complete">
                <div class="grid gap-3">
                    <x-input
                        wire:model="company_address"
                        label="{{ __('app.settings.company.address') }}"
                        icon="o-map-pin"
                        autofocus
                    />
                    <div class="grid grid-cols-2 gap-3">
                        <x-input
                            wire:model="company_postal_code"
                            label="{{ __('app.settings.company.postal_code') }}"
                        />
                        <x-input
                            wire:model="company_city"
                            label="{{ __('app.settings.company.city') }}"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <x-input
                            wire:model="company_province"
                            label="{{ __('app.settings.company.province') }}"
                            placeholder="RM"
                        />
                        <x-input
                            wire:model="company_country"
                            label="{{ __('app.settings.company.country') }}"
                            placeholder="IT"
                        />
                    </div>

                    <hr class="border-base-300 my-2" />
                    <div class="text-sm text-accent font-medium">{{ __('app.settings.company.electronic_invoicing') }}</div>

                    <x-input
                        wire:model="company_pec"
                        label="{{ __('app.settings.company.pec') }}"
                        icon="o-at-symbol"
                        placeholder="azienda@pec.it"
                    />
                    <x-input
                        wire:model="company_sdi_code"
                        label="{{ __('app.settings.company.sdi_code') }}"
                        icon="o-cpu-chip"
                        placeholder="0000000"
                    />

                    <hr class="border-base-300 my-2" />
                    <div class="text-sm text-accent font-medium">{{ __('app.conservation.section_title') }}</div>

                    <x-alert
                        :description="__('app.conservation.setup_description')"
                        icon="o-information-circle"
                        variant="info"
                    >
                        <x-slot:actions>
                            <x-button
                                :label="__('app.conservation.link_label')"
                                icon-right="o-arrow-top-right-on-square"
                                link="https://ivaservizi.agenziaentrate.gov.it"
                                external
                                variant="outline" size="sm"
                            />
                        </x-slot:actions>
                    </x-alert>

                    <x-checkbox
                        wire:model="conservation_acknowledged"
                        :label="__('app.conservation.setup_acknowledge_label')"
                        :hint="__('app.conservation.setup_acknowledge_hint')"
                    />
                </div>

                <x-slot:actions>
                    <div class="flex justify-between items-center w-full">
                        <x-button
                            label="{{ __('app.setup.back') }}"
                            wire:click="previousStep"
                            icon="o-arrow-left"
                            variant="ghost"
                        />
                        <x-button
                            label="{{ __('app.setup.complete') }}"
                            type="submit"
                            icon="o-check"
                            variant="primary"
                            spinner="complete"
                        />
                    </div>
                </x-slot:actions>
            </x-form>
        </div>
    @endif
</div>
