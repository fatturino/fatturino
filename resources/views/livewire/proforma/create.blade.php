<div>
    <x-header :title="__('app.proforma.create_title')" separator>
        <x-slot:actions>
            <x-button :label="__('app.proforma.reverse_calc_title')" wire:click="openReverseCalcModal" icon="o-calculator" variant="outline" size="sm" />
        </x-slot:actions>
    </x-header>

    <form wire:submit="save">
        <div class="grid lg:grid-cols-3 gap-6">

            {{-- LEFT COLUMN: Header fields + Invoice lines --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Header fields --}}
                <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
                    <x-input :label="__('app.proforma.number')" wire:model="number" :hint="$sequenceName" readonly />
                    <x-datetime :label="__('app.proforma.date')" wire:model="date" type="date" />
                    <x-select :label="__('app.proforma.customer')" :options="$contacts" wire:model.live="contact_id" search :placeholder="__('app.proforma.select_customer')" placholder-value="null" />
                </div>

                @include('livewire.invoices.partials._invoice-lines-editor', [
                    'lines'       => $lines,
                    'vatRates'    => $vatRates,
                    'showDiscount' => false,
                ])
            </div>

            {{-- RIGHT COLUMN: Sticky sidebar --}}
            <div class="lg:col-span-1">
                <div class="lg:sticky lg:top-4 space-y-4">

                    @include('livewire.invoices.partials._tax-options-section', [
                        'vatRates'       => $vatRates,
                        'showVatOptions' => false,
                    ])

                    @include('livewire.invoices.partials._totals-sidebar')

                    {{-- Action buttons --}}
                    <div class="flex flex-col gap-2">
                        <x-button :label="__('app.common.save')" wire:click="save" icon="o-check" variant="primary" class="w-full" spinner="save" />
                        <x-button :label="__('app.common.cancel')" link="/proforma" icon="o-x-mark" variant="ghost" class="w-full" />
                    </div>
                </div>
            </div>
        </div>
    </form>

    @include('livewire.invoices.partials._reverse-calc-modal', [
        'vatRates' => $vatRates,
    ])
</div>
