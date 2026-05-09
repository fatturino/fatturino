<div>
    <x-header :title="__('app.proforma.create_title')" separator>
        <x-slot:actions>
            <x-button :label="__('app.common.back')" link="/proforma" icon="o-arrow-left" variant="ghost" />
            <x-button :label="__('app.common.save')" wire:click="save" icon="o-check" variant="primary" spinner="save" />
        </x-slot:actions>
    </x-header>

    <form wire:submit="save">
        <div class="bg-base-100 rounded-xl border border-base-200 p-5 lg:p-6">
        <div class="grid lg:grid-cols-3 gap-6">

            {{-- LEFT COLUMN: Header fields + Invoice lines --}}
            <div class="lg:col-span-2 space-y-6">

                <x-card :title="__('app.proforma.header_section')" separator>
                    <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
                    <x-input :label="__('app.proforma.number')" wire:model="number" :hint="$sequenceName" readonly />
                    <x-datetime :label="__('app.proforma.date')" wire:model="date" type="date" />
                    <x-select :label="__('app.proforma.customer')" :options="$contacts" wire:model.live="contact_id" search :placeholder="__('app.proforma.select_customer')" placholder-value="null" />
                </div>

                </x-card>

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
