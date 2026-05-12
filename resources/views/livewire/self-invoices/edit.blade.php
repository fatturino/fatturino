<div>
    <x-header :title="__('app.self_invoices.edit_title', ['number' => $selfInvoice->number])" separator>
        <x-slot:actions>
            <x-button :label="__('app.common.back')" link="/self-invoices" icon="o-arrow-left" variant="ghost" />
            <x-button :label="__('app.invoices.payment_section')" wire:click="openPaymentModal" icon="o-credit-card" variant="outline" size="sm" />
            @unless($isReadOnly)
                <x-button :label="__('app.common.save')" wire:click="save" icon="o-check" variant="primary" spinner="save" />
            @endunless
        </x-slot:actions>
    </x-header>

    {{-- Read-only banner --}}
    @if($isSdiLocked)
        <x-alert
            :title="__('app.invoices.sdi_locked_banner')"
            icon="o-lock-closed"
            variant="info" class="mb-4"
        />
    @elseif($isReadOnly)
        <x-alert
            :title="__('app.invoices.readonly_banner', ['year' => $selfInvoice->date->year])"
            icon="o-lock-closed"
            variant="warning" class="mb-4"
        />
    @endif

    <x-tabs wire:model="activeTab">
        <x-slot:tabs>
            <x-tab name="details" :label="__('app.audit.tab_details')" icon="o-document-text" />
            <x-tab name="history" :label="__('app.audit.tab_history')" icon="o-clock" />
        </x-slot:tabs>

        <x-tabs.panel name="details">
            <form wire:submit="save">
        <div class="bg-base-100 rounded-xl border border-base-200 p-5 lg:p-6">
        <div class="grid lg:grid-cols-3 gap-6">

            {{-- LEFT COLUMN --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Header fields --}}
                <div @class(['grid grid-cols-2 lg:grid-cols-4 gap-4', 'pointer-events-none' => $isReadOnly])>
                    <x-select :label="__('app.self_invoices.sequence')" :options="$sequences" wire:model.live="sequence_id" />
                    <x-input :label="__('app.self_invoices.number')" wire:model="number" />
                    <x-datetime :label="__('app.self_invoices.date')" wire:model="date" type="date" />
                    <x-select :label="__('app.self_invoices.supplier')" :options="$contacts" wire:model.live="contact_id" search :placeholder="__('app.self_invoices.select_supplier')" placeholder-value="null" />
                </div>

                {{-- Original foreign invoice reference (DatiFattureCollegate) --}}
                <div @class(['bg-base-100 rounded-xl border border-base-200 p-4', 'pointer-events-none' => $isReadOnly])>
                    <h3 class="font-semibold text-base mb-1">{{ __('app.self_invoices.related_invoice_section') }}</h3>
                    <p class="text-sm text-base-content/60 mb-4">{{ __('app.self_invoices.related_invoice_hint') }}</p>
                    <div class="grid grid-cols-3 gap-4">
                        <x-select :label="__('app.self_invoices.document_type')" :options="$documentTypeOptions" option-label="name" option-value="id" wire:model="document_type" />
                        <x-input :label="__('app.self_invoices.related_invoice_number')" wire:model="related_invoice_number" :placeholder="__('app.self_invoices.related_invoice_number_placeholder')" />
                        <x-datetime :label="__('app.self_invoices.related_invoice_date')" wire:model="related_invoice_date" type="date" />
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
                    @if($selfInvoice->sdi_status)
                        @php
                            $sdiColor = match($selfInvoice->sdi_status->value) {
                                'sent'  => 'success',
                                'error' => 'danger',
                                default => 'neutral',
                            };
                        @endphp
                        <div class="flex items-center gap-2">
                            <x-badge :value="'SDI: ' . $selfInvoice->sdi_status->value" :variant="$sdiColor" />
                            @if($selfInvoice->sdi_message)
                                <span class="text-sm text-base-content/60">{{ $selfInvoice->sdi_message }}</span>
                            @endif
                        </div>
                    @endif

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

                        {{-- Original invoice reference summary --}}
                        @if($selfInvoice->related_invoice_number)
                            <hr class="my-3" />
                            <div class="text-sm space-y-1 text-base-content/70">
                                <p class="font-semibold">{{ __('app.self_invoices.related_invoice_summary') }}</p>
                                <p>{{ $selfInvoice->related_invoice_number }}</p>
                                @if($selfInvoice->related_invoice_date)
                                    <p>{{ $selfInvoice->related_invoice_date->format('d/m/Y') }}</p>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- Workflow step: send to SDI --}}
                    @unless($isReadOnly)
                        <x-button :label="__('app.self_invoices.send_sdi')" wire:click="sendToSdi" icon="o-paper-airplane" variant="warning" class="w-full" spinner="sendToSdi" :disabled="!$sdiConfigured" />
                    @endunless
                    @if(!$sdiConfigured && !$isReadOnly)
                        <p class="text-xs text-base-content/50 text-center">{{ __('app.invoices.sdi_not_configured_hint') }}</p>
                    @endif

                    {{-- Document actions --}}
                    <div class="flex items-center gap-1 pt-1">
                        @unless($isReadOnly)
                            <x-button :label="__('app.self_invoices.download_xml')" wire:click="downloadXml" icon="o-arrow-down-tray" variant="ghost" size="sm" spinner="downloadXml" />
                        @endunless
                    </div>

                    {{-- Cancel --}}
                    <div class="text-center pt-2">
                        <x-button :label="__('app.common.cancel')" link="{{ route('self-invoices.index') }}" icon="o-x-mark" variant="ghost" size="sm" />
                    </div>

                    {{-- Recent activity summary (latest SDI status + last email sent) --}}
                    @if($sdiLogs->isNotEmpty() || $latestEmailAudit)
                        <div class="bg-base-100 rounded-xl border border-base-200 overflow-hidden">
                            @if($sdiLogs->isNotEmpty())
                                @php $latestLog = $sdiLogs->first(); @endphp
                                <div class="p-4 flex items-center gap-3">
                                    <div class="rounded-xl p-2.5 shrink-0 {{ $latestLog->status->iconBgClass() }}">
                                        <x-icon :name="$latestLog->status->icon()" class="w-5 h-5 {{ $latestLog->status->iconColorClass() }}" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-[10px] font-semibold uppercase tracking-wider opacity-40">{{ __('app.invoices.sdi_log_title') }}</p>
                                        <p class="font-semibold text-sm mt-0.5">{{ $latestLog->status->label() }}</p>
                                    </div>
                                    <time class="text-xs opacity-30 shrink-0 self-start">{{ $latestLog->created_at->format('d/m H:i') }}</time>
                                </div>
                            @endif

                            @if($latestEmailAudit)
                                <div @class([
                                    'p-4 flex items-center gap-3',
                                    'border-t border-base-200' => $sdiLogs->isNotEmpty(),
                                ])>
                                    <div class="rounded-xl p-2.5 shrink-0 bg-base-200">
                                        <x-icon name="o-envelope" class="w-5 h-5" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-[10px] font-semibold uppercase tracking-wider opacity-40">{{ __('app.audit.events.email_sent') }}</p>
                                        <p class="text-xs opacity-50 mt-0.5">{{ $latestEmailAudit->created_at->translatedFormat('d M Y H:i') }}</p>
                                    </div>
                                </div>
                            @endif

                            <div class="border-t border-base-200 p-2 text-center">
                                <button type="button" wire:click="$set('activeTab', 'history')" class="text-xs text-base-content/60 hover:text-primary">
                                    {{ __('app.audit.tab_history') }} →
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        </div>
            </form>
        </x-tabs.panel>

        <x-tabs.panel name="history">
            @livewire('invoices.invoice-timeline', ['invoice' => $selfInvoice], key('timeline-'.$selfInvoice->id))
        </x-tabs.panel>
    </x-tabs>

    {{-- Payment modal --}}
    <x-payment-modal />
</div>
