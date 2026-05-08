<div>
    <x-header :title="__('app.credit_notes.create_title')" separator />

    <form wire:submit="save">
        <div class="grid lg:grid-cols-3 gap-6">

            {{-- LEFT COLUMN --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Header fields --}}
                <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
                    <x-input :label="__('app.credit_notes.number')" wire:model="number" :hint="$sequenceName" readonly />
                    <x-datetime :label="__('app.credit_notes.date')" wire:model="date" type="date" />
                    <x-select
                        :label="__('app.credit_notes.customer')"
                        :options="$contacts"
                        wire:model.live="contact_id"
                        search
                        :placeholder="__('app.credit_notes.select_customer')"
                        placeholder-value="null"
                    />
                </div>

                {{-- Notes --}}
                <x-textarea :label="__('app.credit_notes.notes')" wire:model="notes" rows="2" />

                {{-- Original invoice reference (DatiFattureCollegate in XML) --}}
                <div class="bg-base-100 rounded-xl border border-base-200 p-4">
                    <h3 class="font-semibold text-base mb-1">{{ __('app.credit_notes.related_invoice_section') }}</h3>
                    <p class="text-sm text-base-content/60 mb-4">{{ __('app.credit_notes.related_invoice_hint') }}</p>
                    <div class="grid grid-cols-2 gap-4">
                        <x-input
                            :label="__('app.credit_notes.related_invoice_number')"
                            wire:model="related_invoice_number"
                            :placeholder="__('app.credit_notes.related_invoice_number_placeholder')"
                        />
                        <x-datetime :label="__('app.credit_notes.related_invoice_date')" wire:model="related_invoice_date" type="date" />
                    </div>
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

                    {{-- Sequence selector --}}
                    <x-select
                        :label="__('app.self_invoices.sequence')"
                        :options="$sequences"
                        wire:model.live="sequence_id"
                        option-label="name"
                    />

                    {{-- Totals --}}
                    <div class="bg-base-100 rounded-xl border border-base-200 p-5">
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-base-content/70">{{ __('app.credit_notes.net_total') }}</span>
                                <span>€ {{ number_format($this->totalNet, 2, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-base-content/70">{{ __('app.credit_notes.vat_total') }}</span>
                                <span>€ {{ number_format($this->totalVat, 2, ',', '.') }}</span>
                            </div>

                            <hr class="my-1" />

                            <div class="flex justify-between font-bold text-lg">
                                <span>{{ __('app.credit_notes.grand_total') }}</span>
                                <span>€ {{ number_format($this->totalGross, 2, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Action buttons --}}
                    <div class="flex flex-col gap-2">
                        <x-button :label="__('app.common.save')" wire:click="save" icon="o-check" variant="primary" class="w-full" spinner="save" />
                        <x-button :label="__('app.common.cancel')" link="{{ route('credit-notes.index') }}" icon="o-x-mark" variant="ghost" class="w-full" />
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
