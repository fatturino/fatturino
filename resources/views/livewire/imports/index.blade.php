<div>
    <x-header :title="__('app.imports.title')" separator />

    {{-- XML Standard Section --}}
    <div class="mb-8">
        <h3 class="font-semibold text-base mb-1">{{ __('app.imports.xml_section') }}</h3>
        <p class="text-sm text-base-content/60 mb-4">{{ __('app.imports.xml_section_desc') }}</p>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">

            {{-- XML Fatture Vendite --}}
            <x-card>
                <div class="flex items-start gap-4 mb-4">
                    <div class="p-3 rounded-lg bg-primary/10">
                        <x-icon name="o-document-arrow-up" class="w-8 h-8 text-primary" />
                    </div>
                    <div>
                        <p class="font-semibold">{{ __('app.imports.xml_sales_title') }}</p>
                        <p class="text-sm text-base-content/60 mt-1">{{ __('app.imports.xml_sales_desc') }}</p>
                    </div>
                </div>
                <div class="flex justify-end">
                    <x-button
                        :label="__('app.imports.start_import')"
                        icon="o-arrow-down-tray"
                        variant="primary" size="sm"
                        wire:click="openImport('xml_sales')"
                    />
                </div>
            </x-card>

            {{-- XML Fatture Acquisti --}}
            <x-card>
                <div class="flex items-start gap-4 mb-4">
                    <div class="p-3 rounded-lg bg-secondary/10">
                        <x-icon name="o-document-arrow-down" class="w-8 h-8 text-secondary" />
                    </div>
                    <div>
                        <p class="font-semibold">{{ __('app.imports.xml_purchase_title') }}</p>
                        <p class="text-sm text-base-content/60 mt-1">{{ __('app.imports.xml_purchase_desc') }}</p>
                    </div>
                </div>
                <div class="flex justify-end">
                    <x-button
                        :label="__('app.imports.start_import')"
                        icon="o-arrow-down-tray"
                        variant="secondary" size="sm"
                        wire:click="openImport('xml_purchase')"
                    />
                </div>
            </x-card>

            {{-- XML Autofatture --}}
            <x-card>
                <div class="flex items-start gap-4 mb-4">
                    <div class="p-3 rounded-lg bg-accent/10">
                        <x-icon name="o-document-duplicate" class="w-8 h-8 text-accent" />
                    </div>
                    <div>
                        <p class="font-semibold">{{ __('app.imports.xml_self_invoice_title') }}</p>
                        <p class="text-sm text-base-content/60 mt-1">{{ __('app.imports.xml_self_invoice_desc') }}</p>
                    </div>
                </div>
                <div class="flex justify-end">
                    <x-button
                        :label="__('app.imports.start_import')"
                        icon="o-arrow-down-tray"
                        variant="accent" size="sm"
                        wire:click="openImport('xml_self_invoice')"
                    />
                </div>
            </x-card>

        </div>
    </div>

    {{-- Third-party Platforms Section --}}
    <div>
        <h3 class="font-semibold text-base mb-1">{{ __('app.imports.platforms_section') }}</h3>
        <p class="text-sm text-base-content/60 mb-4">{{ __('app.imports.platforms_section_desc') }}</p>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">

            {{-- Fattura24 - Rubrica --}}
            <x-card>
                <div class="flex items-start gap-4 mb-4">
                    <div class="p-3 rounded-lg bg-warning/10">
                        <x-icon name="o-user-group" class="w-8 h-8 text-warning" />
                    </div>
                    <div>
                        <p class="font-semibold">{{ __('app.imports.fattura24_contacts_title') }}</p>
                        <p class="text-sm text-base-content/60 mt-1">{{ __('app.imports.fattura24_contacts_desc') }}</p>
                    </div>
                </div>
                <div class="flex justify-end">
                    <x-button
                        :label="__('app.imports.start_import')"
                        icon="o-arrow-down-tray"
                        variant="warning" size="sm"
                        wire:click="openImport('fattura24_contacts')"
                    />
                </div>
            </x-card>

            {{-- Aruba - Rubrica (coming soon) --}}
            <x-card class="opacity-50 cursor-not-allowed">
                <div class="flex items-start gap-4 mb-4">
                    <div class="p-3 rounded-lg bg-info/10">
                        <x-icon name="o-user-group" class="w-8 h-8 text-info" />
                    </div>
                    <div>
                        <p class="font-semibold">{{ __('app.imports.aruba_contacts_title') }}</p>
                        <p class="text-sm text-base-content/60 mt-1">{{ __('app.imports.aruba_contacts_desc') }}</p>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <x-badge value="Prossimamente" variant="info" type="soft" />
                </div>
            </x-card>

            {{-- Fatture in Cloud - Rubrica (coming soon) --}}
            <x-card class="opacity-50 cursor-not-allowed">
                <div class="flex items-start gap-4 mb-4">
                    <div class="p-3 rounded-lg bg-success/10">
                        <x-icon name="o-user-group" class="w-8 h-8 text-success" />
                    </div>
                    <div>
                        <p class="font-semibold">{{ __('app.imports.fic_contacts_title') }}</p>
                        <p class="text-sm text-base-content/60 mt-1">{{ __('app.imports.fic_contacts_desc') }}</p>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <x-badge value="Prossimamente" variant="info" type="soft" />
                </div>
            </x-card>

        </div>
    </div>

    {{-- Shared Import Modal --}}
    <x-modal
        wire:model="showModal"
        :title="$importType ? __('app.imports.' . $importType . '_title') : ''"
    >
        @if ($importResult)

            {{-- Results View --}}
            <div class="space-y-4">

                @if (empty($importResult['errors']))
                    <x-alert variant="success" class="text-sm">
                        {{ __('app.imports.completed_no_errors') }}
                    </x-alert>
                @else
                    <x-alert variant="warning" class="text-sm">
                        {{ __('app.imports.completed_with_errors') }}
                    </x-alert>
                @endif

                {{-- Stats Grid --}}
                <div class="grid grid-cols-2 gap-3">
                    @if (in_array($importResult['type'], ['xml_sales', 'xml_purchase', 'xml_self_invoice']))
                        <div class="bg-base-200 rounded-box p-4 text-center">
                            <div class="text-2xl font-bold">{{ $importResult['stats']['invoices_imported'] }}</div>
                            <div class="text-xs text-base-content/60 mt-1">{{ __('app.imports.stat_invoices_imported') }}</div>
                        </div>
                        <div class="bg-base-200 rounded-box p-4 text-center">
                            <div class="text-2xl font-bold">{{ $importResult['stats']['contacts_created'] }}</div>
                            <div class="text-xs text-base-content/60 mt-1">{{ __('app.imports.stat_contacts_created') }}</div>
                        </div>
                        <div class="bg-base-200 rounded-box p-4 text-center">
                            <div class="text-2xl font-bold">{{ $importResult['stats']['skipped'] }}</div>
                            <div class="text-xs text-base-content/60 mt-1">{{ __('app.imports.stat_skipped') }}</div>
                        </div>
                        <div class="bg-base-200 rounded-box p-4 text-center">
                            <div class="text-2xl font-bold {{ $importResult['stats']['errors'] > 0 ? 'text-error' : '' }}">
                                {{ $importResult['stats']['errors'] }}
                            </div>
                            <div class="text-xs text-base-content/60 mt-1">{{ __('app.imports.stat_errors') }}</div>
                        </div>
                    @elseif ($importResult['type'] === 'fattura24_contacts')
                        <div class="bg-base-200 rounded-box p-4 text-center">
                            <div class="text-2xl font-bold">{{ $importResult['stats']['total'] }}</div>
                            <div class="text-xs text-base-content/60 mt-1">{{ __('app.imports.stat_total') }}</div>
                        </div>
                        <div class="bg-base-200 rounded-box p-4 text-center">
                            <div class="text-2xl font-bold text-success">{{ $importResult['stats']['imported'] }}</div>
                            <div class="text-xs text-base-content/60 mt-1">{{ __('app.imports.stat_imported') }}</div>
                        </div>
                        <div class="bg-base-200 rounded-box p-4 text-center">
                            <div class="text-2xl font-bold text-info">{{ $importResult['stats']['updated'] }}</div>
                            <div class="text-xs text-base-content/60 mt-1">{{ __('app.imports.stat_updated') }}</div>
                        </div>
                        <div class="bg-base-200 rounded-box p-4 text-center">
                            <div class="text-2xl font-bold">{{ $importResult['stats']['skipped'] }}</div>
                            <div class="text-xs text-base-content/60 mt-1">{{ __('app.imports.stat_skipped') }}</div>
                        </div>
                        <div class="bg-base-200 rounded-box p-4 text-center col-span-2">
                            <div class="text-2xl font-bold {{ $importResult['stats']['errors'] > 0 ? 'text-error' : '' }}">
                                {{ $importResult['stats']['errors'] }}
                            </div>
                            <div class="text-xs text-base-content/60 mt-1">{{ __('app.imports.stat_errors') }}</div>
                        </div>
                    @endif
                </div>

                {{-- Error detail list --}}
                @if (!empty($importResult['errors']))
                    <div class="bg-error/10 rounded-box p-4 max-h-40 overflow-y-auto">
                        <p class="text-sm font-semibold text-error mb-2">{{ __('app.imports.error_details') }}</p>
                        <ul class="text-xs space-y-1">
                            @foreach ($importResult['errors'] as $errorMessage)
                                <li class="text-error">{{ $errorMessage }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

            </div>

        @else

            {{-- Import Form --}}
            @if (in_array($importType, ['xml_sales', 'xml_purchase', 'xml_self_invoice']))
                <div class="space-y-4">
                    <x-file
                        wire:model="xmlFile"
                        :label="__('app.imports.xml_file_label')"
                        accept=".xml,.p7m,.zip"
                        :hint="__('app.imports.xml_file_hint')"
                    />
                </div>
            @elseif ($importType === 'fattura24_contacts')
                <div class="space-y-4">
                    <x-file
                        wire:model="csvFile"
                        :label="__('app.imports.csv_file_label')"
                        accept=".csv"
                        :hint="__('app.imports.csv_file_hint')"
                    />

                    <x-checkbox
                        wire:model="updateExisting"
                        :label="__('app.imports.update_existing')"
                        :hint="__('app.imports.update_existing_hint')"
                    />
                </div>
            @endif

        @endif

        <x-slot:actions>
            @if ($importResult)
                <x-button :label="__('app.imports.import_another')" wire:click="resetImport" />
                <x-button :label="__('app.common.done')" @click="$wire.showModal = false" variant="primary" />
            @else
                <x-button :label="__('app.common.cancel')" @click="$wire.showModal = false" />
                <x-button
                    :label="__('app.imports.run_import')"
                    icon="o-arrow-down-tray"
                    variant="primary"
                    wire:click="runImport"
                    spinner="runImport"
                />
            @endif
        </x-slot:actions>
    </x-modal>
</div>
