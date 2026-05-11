<div>
    <x-header :title="__('app.invoices.create_title')" separator>
        <x-slot:actions>
            <x-button :label="__('app.common.back')" link="/sell-invoices" icon="o-arrow-left" variant="ghost" />
            <x-button :label="__('app.invoices.reverse_calc_title')" wire:click="openReverseCalcModal" icon="o-calculator" variant="outline" size="sm" />
            <x-button :label="__('app.common.save')" wire:click="save" icon="o-check" variant="primary" spinner="save" />
        </x-slot:actions>
    </x-header>

    <form wire:submit="save">
        <div class="bg-base-100 rounded-xl border border-base-200 p-5 lg:p-6">
        <div class="grid lg:grid-cols-3 gap-6">

            {{-- LEFT COLUMN: Header + Notes + Lines --}}
            <div class="lg:col-span-2 space-y-6">

                <x-card :title="__('app.invoices.header_section')" separator>
                    @include('livewire.invoices.partials._header-fields', [
                        'mode' => 'create',
                        'contacts' => $contacts,
                        'sequenceName' => $sequenceName,
                    ])
                </x-card>

                <x-textarea :label="__('app.invoices.notes_label')" wire:model="notes" rows="2" />

                @include('livewire.invoices.partials._invoice-lines-editor', [
                    'lines' => $lines,
                    'vatRates' => $vatRates,
                ])
            </div>

            {{-- RIGHT COLUMN: Sticky — Tax + Payment + Totals --}}
            <div class="lg:col-span-1">
                <div class="lg:sticky lg:top-4 space-y-4">

                    @include('livewire.invoices.partials._tax-options-section', [
                        'vatRates' => $vatRates,
                    ])

                    @include('livewire.invoices.partials._payment-details-section')

                    @include('livewire.invoices.partials._totals-sidebar')

                    {{-- Autosave indicator --}}
                    <div class="text-center"
                         x-data="{ lastSaved: '' }"
                         x-init="
                            setInterval(() => { $wire.saveDraft(); lastSaved = $wire.draftSavedAt; }, 30000);
                            $watch('$wire.draftSavedAt', v => lastSaved = v);
                         ">
                        <span x-show="lastSaved" x-cloak class="text-xs text-base-content/40 inline-flex items-center gap-1">
                            <x-icon name="o-check-circle" class="w-3 h-3 text-success" />
                            {{ __('app.invoices.draft_saved') }} <span x-text="lastSaved"></span>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </form>

    @include('livewire.invoices.partials._reverse-calc-modal', [
        'vatRates' => $vatRates,
    ])
</div>
