<div>
    <x-header :title="__('app.purchase_invoices.create_title')" separator />

    <form wire:submit="save">
        <div class="grid lg:grid-cols-3 gap-6">

            {{-- LEFT COLUMN --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Header fields --}}
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <x-select :label="__('app.purchase_invoices.sequence')" :options="$sequences" wire:model.live="sequence_id" />
                    <x-input :label="__('app.purchase_invoices.number')" wire:model="number" readonly />
                    <x-datetime :label="__('app.purchase_invoices.date')" wire:model="date" type="date" />
                    <x-select :label="__('app.purchase_invoices.supplier')" :options="$contacts" wire:model.live="contact_id" search :placeholder="__('app.purchase_invoices.select_supplier')" placholder-value="null" />
                </div>

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
                                <span class="text-base-content/70">{{ __('app.purchase_invoices.net_total') }}</span>
                                <span>€ {{ number_format($this->totalNet, 2, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-base-content/70">{{ __('app.purchase_invoices.vat_total') }}</span>
                                <span>€ {{ number_format($this->totalVat, 2, ',', '.') }}</span>
                            </div>

                            <hr class="my-1" />

                            <div class="flex justify-between font-bold text-lg">
                                <span>{{ __('app.purchase_invoices.grand_total') }}</span>
                                <span>€ {{ number_format($this->totalGross, 2, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Action buttons --}}
                    <div class="flex flex-col gap-2">
                        <x-button :label="__('app.common.save')" wire:click="save" icon="o-check" variant="primary" class="w-full" spinner="save" />
                        <x-button :label="__('app.common.cancel')" link="{{ route('purchase-invoices.index') }}" icon="o-x-mark" variant="ghost" class="w-full" />
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
