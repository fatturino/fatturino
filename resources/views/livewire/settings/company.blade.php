<div>
    <x-header :title="__('app.settings.company.title')" separator>
        @allowed('edit-company-settings')
            <x-slot:actions>
                <x-button :label="__('app.common.save')" wire:click="save" icon="o-check" class="btn-primary" spinner="save" />
            </x-slot:actions>
        @endallowed
    </x-header>

    @allowed('edit-company-settings')
    @elseallowed
        <x-alert :title="__('app.settings.company.readonly_title')" :description="__('app.settings.company.readonly_description')" icon="o-lock-closed" class="alert-warning mb-6" />
    @endallowed

    <x-form wire:submit="save">
        <div class="grid lg:grid-cols-2 gap-5">
            {{-- General Info --}}
            <x-card :title="__('app.settings.company.general_info')" separator>
                <div class="grid gap-3">
                    <x-input :label="__('app.settings.company.company_name')" wire:model="company_name" :disabled="$readonly" />
                    <x-input :label="__('app.settings.company.vat_number')" wire:model="company_vat_number" :disabled="$readonly" />
                    <x-input :label="__('app.settings.company.tax_code')" wire:model="company_tax_code" :disabled="$readonly" />
                </div>
            </x-card>

            {{-- Address --}}
            <x-card :title="__('app.settings.company.address_section')" separator>
                <div class="grid gap-3">
                    <x-input :label="__('app.settings.company.address')" wire:model="company_address" :disabled="$readonly" />
                    <div class="grid grid-cols-2 gap-3">
                        <x-input :label="__('app.settings.company.postal_code')" wire:model="company_postal_code" :disabled="$readonly" />
                        <x-input :label="__('app.settings.company.city')" wire:model="company_city" :disabled="$readonly" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <x-input :label="__('app.settings.company.province')" wire:model="company_province" :disabled="$readonly" />
                        <x-input :label="__('app.settings.company.country')" wire:model="company_country" :disabled="$readonly" />
                    </div>
                </div>
            </x-card>

            {{-- Electronic Invoicing --}}
            <x-card :title="__('app.settings.company.electronic_invoicing')" separator>
                <div class="grid gap-3">
                    <x-select
                        :label="__('app.settings.company.fiscal_regime')"
                        wire:model="company_fiscal_regime"
                        :options="$fiscalRegimes"
                        option-value="id"
                        option-label="name"
                        icon="o-scale"
                        :disabled="$readonly"
                    />
                    <x-input :label="__('app.settings.company.pec')" wire:model="company_pec" :disabled="$readonly" />
                    <x-input :label="__('app.settings.company.sdi_code')" wire:model="company_sdi_code" :disabled="$readonly" />
                </div>
            </x-card>

            {{-- ATECO Codes --}}
            <x-card :title="__('app.settings.company.ateco_section')" separator>
                <div class="grid gap-3">
                    {{-- Search input --}}
                    <x-input
                        wire:model.live.debounce.300ms="ateco_search"
                        :placeholder="__('app.settings.company.ateco_search_placeholder')"
                        icon="o-magnifying-glass"
                        :disabled="$readonly"
                        clearable
                    />

                    {{-- Search results list --}}
                    @if(mb_strlen(trim($ateco_search)) >= 2)
                        @php $results = $this->atecoSearchResults(); @endphp
                        @if(count($results) > 0)
                            <div class="border border-base-300 rounded-lg max-h-56 overflow-y-auto divide-y divide-base-200">
                                @foreach($results as $option)
                                    <button
                                        type="button"
                                        wire:click="addAtecoCode('{{ $option['id'] }}')"
                                        class="w-full text-left px-3 py-2 text-sm hover:bg-base-200 transition-colors"
                                    >
                                        {{ $option['name'] }}
                                    </button>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-base-content/50">{{ __('app.settings.company.ateco_no_results') }}</p>
                        @endif
                    @endif

                    {{-- Selected codes list --}}
                    @if(count($company_ateco_codes) > 0)
                        <div class="grid gap-1 mt-1">
                            @foreach($company_ateco_codes as $codice)
                                <div class="flex items-center justify-between gap-2 px-3 py-2 bg-base-200 rounded-lg text-sm">
                                    <span class="font-mono font-semibold text-xs text-primary mr-1">{{ $codice }}</span>
                                    <span class="flex-1 text-base-content">{{ \App\Enums\AtecoCode::find($codice)['titolo'] ?? $codice }}</span>
                                    @if(!$readonly)
                                        <button
                                            type="button"
                                            wire:click="removeAtecoCode('{{ $codice }}')"
                                            class="text-base-content/40 hover:text-error transition-colors shrink-0"
                                        >
                                            <x-icon name="o-x-mark" class="w-4 h-4" />
                                        </button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </x-card>

            {{-- Logo --}}
            <x-card :title="__('app.settings.company.logo_section')" separator>
                <div class="grid gap-4">
                    @if ($company_logo_path)
                        <div class="flex items-center gap-4">
                            <img
                                src="{{ Storage::disk('public')->url($company_logo_path) }}"
                                alt="{{ __('app.settings.company.logo_preview_alt') }}"
                                class="max-h-16 max-w-48 object-contain border rounded p-1"
                            />
                            @allowed('edit-company-settings')
                                <x-button
                                    :label="__('app.settings.company.remove_logo')"
                                    wire:click="removeLogo"
                                    icon="o-trash"
                                    class="btn-ghost btn-sm text-error"
                                    spinner="removeLogo"
                                />
                            @endallowed
                        </div>
                    @endif

                    @allowed('edit-company-settings')
                        <x-file
                            wire:model="company_logo"
                            :label="__('app.settings.company.logo_upload')"
                            :hint="__('app.settings.company.logo_hint')"
                            accept="image/png,image/jpeg"
                        />
                    @endallowed
                </div>
            </x-card>

        </div>

    </x-form>
</div>
