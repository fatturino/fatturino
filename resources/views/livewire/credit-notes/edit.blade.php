<div>
    <x-header :title="__('app.credit_notes.edit_title', ['number' => $creditNote->number])" separator>
        <x-slot:actions>
            <x-button :label="__('app.invoices.payment_section')" wire:click="openPaymentModal" icon="o-credit-card" variant="outline" size="sm" />
        </x-slot:actions>
    </x-header>

    {{-- Read-only banner --}}
    @if($isReadOnly)
        <x-alert
            :title="__('app.credit_notes.readonly_error')"
            icon="o-lock-closed"
            variant="warning" class="mb-4"
        />
    @endif

    <form wire:submit="save">
        <div class="bg-base-100 rounded-xl border border-base-200 p-5 lg:p-6">
        <div class="grid lg:grid-cols-3 gap-6">

            {{-- LEFT COLUMN --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Header fields --}}
                <div @class(['grid grid-cols-2 lg:grid-cols-4 gap-4', 'pointer-events-none' => $isReadOnly])>
                    <x-select :label="__('app.self_invoices.sequence')" :options="$sequences" wire:model.live="sequence_id" option-label="name" />
                    <x-input :label="__('app.credit_notes.number')" wire:model="number" />
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
                <div @class(['pointer-events-none' => $isReadOnly])>
                    <x-textarea :label="__('app.credit_notes.notes')" wire:model="notes" rows="2" />
                </div>

                {{-- Original invoice reference (DatiFattureCollegate) --}}
                <div @class(['bg-base-100 rounded-xl border border-base-200 p-4', 'pointer-events-none' => $isReadOnly])>
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
                    'isReadOnly'   => $isReadOnly,
                ])
            </div>

            {{-- RIGHT COLUMN: Sticky sidebar --}}
            <div class="lg:col-span-1">
                <div class="lg:sticky lg:top-4 space-y-4">

                    {{-- SDI status badge --}}
                    @if($creditNote->sdi_status)
                        <div class="flex items-center gap-2">
                            <x-badge :value="$creditNote->sdi_status->label()" variant="$creditNote->sdi_status->badgeVariant()" type="soft"" />
                            @if($creditNote->sdi_message)
                                <span class="text-sm text-base-content/60">{{ $creditNote->sdi_message }}</span>
                            @endif
                        </div>
                    @endif

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

                        {{-- Original invoice reference summary --}}
                        @if($creditNote->related_invoice_number)
                            <hr class="my-3" />
                            <div class="text-sm space-y-1 text-base-content/70">
                                <p class="font-semibold">{{ __('app.self_invoices.related_invoice_summary') }}</p>
                                <p>{{ $creditNote->related_invoice_number }}</p>
                                @if($creditNote->related_invoice_date)
                                    <p>{{ $creditNote->related_invoice_date->format('d/m/Y') }}</p>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- Action buttons --}}
                    <div class="flex flex-col gap-2">
                        @unless($isReadOnly)
                            <x-button :label="__('app.common.save')" wire:click="save" icon="o-check" variant="primary" class="w-full" spinner="save" />
                            <x-button
                                :label="__('app.credit_notes.send_sdi')"
                                wire:click="sendToSdi"
                                icon="o-paper-airplane"
                                variant="secondary" class="w-full"
                                spinner="sendToSdi"
                                :disabled="!$sdiConfigured"
                            />
                        @endunless
                        @if(!$sdiConfigured && !$isReadOnly)
                            <p class="text-xs text-base-content/50 text-center">{{ __('app.invoices.sdi_not_configured_hint') }}</p>
                        @endif

                        <div class="flex gap-2">
                            <x-button :label="__('app.credit_notes.download_xml')" wire:click="downloadXml" icon="o-arrow-down-tray" variant="ghost" size="sm" class="flex-1" spinner="downloadXml" />
                            <x-button icon="o-document" wire:click="downloadPdf" variant="ghost" size="sm" spinner="downloadPdf" tooltip="PDF" />
                            <x-button :label="__('app.common.cancel')" link="{{ route('credit-notes.index') }}" icon="o-x-mark" variant="ghost" size="sm" class="flex-1" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </form>

    {{-- Payment modal --}}
    <x-payment-modal />
</div>
