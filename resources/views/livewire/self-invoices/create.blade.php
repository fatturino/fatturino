<div>
    <x-header :title="__('app.self_invoices.create_title')" separator>
        <x-slot:actions>
            <x-button :label="__('app.common.back')" link="/self-invoices" icon="o-arrow-left" variant="ghost" />
            <x-button :label="__('app.common.save')" wire:click="save" icon="o-check" variant="primary" spinner="save" />
        </x-slot:actions>
    </x-header>

    <form wire:submit="save">
        <div class="bg-base-100 rounded-xl border border-base-200 p-5 lg:p-6">
        <div class="grid lg:grid-cols-3 gap-6">

            {{-- LEFT COLUMN --}}
            <div class="lg:col-span-2 space-y-6">

                <x-card :title="__('app.self_invoices.header_section')" separator>
                    <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
                    <x-input :label="__('app.self_invoices.number')" wire:model="number" :hint="$sequenceName" readonly />
                    <x-datetime :label="__('app.self_invoices.date')" wire:model="date" type="date" />
                    <x-select :label="__('app.self_invoices.supplier')" :options="$contacts" wire:model.live="contact_id" search :placeholder="__('app.self_invoices.select_supplier')" placeholder-value="null" />
                </div>

                {{-- Original foreign invoice reference (DatiFattureCollegate) --}}
                <div class="bg-base-100 rounded-xl border border-base-200 p-4">
                    <h3 class="font-semibold text-base mb-1">{{ __('app.self_invoices.related_invoice_section') }}</h3>
                    <p class="text-sm text-base-content/60 mb-4">{{ __('app.self_invoices.related_invoice_hint') }}</p>
                    <div class="grid grid-cols-3 gap-4">
                        <x-select :label="__('app.self_invoices.document_type')" :options="$documentTypeOptions" option-label="name" option-value="id" wire:model="document_type" />
                        <x-input :label="__('app.self_invoices.related_invoice_number')" wire:model="related_invoice_number" :placeholder="__('app.self_invoices.related_invoice_number_placeholder')" />
                        <x-datetime :label="__('app.self_invoices.related_invoice_date')" wire:model="related_invoice_date" type="date" />
                    </div>
                </div>

                </x-card>

                @include('livewire.invoices.partials._invoice-lines-editor', [
                    'lines'        => $lines,
                    'vatRates'     => $vatRates,
                    'showDiscount' => false,
                ])
            </div>

            {{-- RIGHT COLUMN: Sticky sidebar --}}
            <div class="lg:col-span-1">
                <div class="lg:sticky lg:top-4 space-y-4">

                    {{-- Totals --}}
                    <div class="bg-base-100 rounded-xl border border-base-200 p-5">
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-base-content/70">{{ __('app.self_invoices.net_total') }}</span>
                                <span>€ {{ number_format($this->totalNet, 2, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-base-content/70">{{ __('app.self_invoices.vat_total') }}</span>
                                <span>€ {{ number_format($this->totalVat, 2, ',', '.') }}</span>
                            </div>

                            <hr class="my-1" />

                            <div class="flex justify-between font-bold text-lg">
                                <span>{{ __('app.self_invoices.grand_total') }}</span>
                                <span>€ {{ number_format($this->totalGross, 2, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Action buttons --}}
                    <div class="flex flex-col gap-2">
                    </div>
                </div>
            </div>
        </div>
        </div>
    </form>
</div>
