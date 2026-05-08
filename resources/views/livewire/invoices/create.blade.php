<div>
    <x-header :title="__('app.invoices.create_title')" separator>
        <x-slot:actions>
            <x-button :label="__('app.invoices.reverse_calc_title')" wire:click="openReverseCalcModal" icon="o-calculator" variant="outline" size="sm" />
        </x-slot:actions>
    </x-header>

    <form wire:submit="save">
        <div class="grid lg:grid-cols-3 gap-6">

            {{-- LEFT COLUMN: Header fields + Invoice lines --}}
            <div class="lg:col-span-2 space-y-6">

                @include('livewire.invoices.partials._header-fields', [
                    'mode' => 'create',
                    'contacts' => $contacts,
                    'sequenceName' => $sequenceName,
                ])

                {{-- Notes / Causale --}}
                <x-textarea :label="__('app.invoices.notes_label')" wire:model="notes" rows="2" />

                @include('livewire.invoices.partials._invoice-lines-editor', [
                    'lines' => $lines,
                    'vatRates' => $vatRates,
                ])
            </div>

            {{-- RIGHT COLUMN: Sticky sidebar --}}
            <div class="lg:col-span-1">
                <div class="lg:sticky lg:top-4 space-y-4">

                    @include('livewire.invoices.partials._tax-options-section', [
                        'vatRates' => $vatRates,
                    ])

                    @include('livewire.invoices.partials._payment-details-section')

                    @include('livewire.invoices.partials._totals-sidebar')

                    {{-- Action buttons --}}
                    <div class="flex flex-col gap-2">
                        <x-button :label="__('app.common.save')" wire:click="save" icon="o-check" variant="primary" class="w-full" spinner="save" />
                        <x-button :label="__('app.common.cancel')" link="/sell-invoices" icon="o-x-mark" variant="ghost" class="w-full" />
                    </div>
                </div>
            </div>
        </div>
    </form>

    @include('livewire.invoices.partials._reverse-calc-modal', [
        'vatRates' => $vatRates,
    ])
</div>
