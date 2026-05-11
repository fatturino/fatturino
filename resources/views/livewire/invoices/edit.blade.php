<div>
    <x-header :title="__('app.invoices.edit_title', ['number' => $invoice->number])" separator>
        <x-slot:actions>
            <x-button :label="__('app.common.back')" link="/sell-invoices" icon="o-arrow-left" variant="ghost" />
            <x-button :label="__('app.invoices.payment_section')" wire:click="openPaymentModal" icon="o-credit-card" variant="outline" size="sm" />
            @unless($isReadOnly)
                <x-button :label="__('app.invoices.reverse_calc_title')" wire:click="openReverseCalcModal" icon="o-calculator" variant="outline" size="sm" />
            @endunless
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
            :title="__('app.invoices.readonly_banner', ['year' => $invoice->date->year])"
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

            {{-- LEFT COLUMN: Header fields + Invoice lines --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Header card --}}
                <x-card :title="__('app.invoices.header_section')" separator>
                    @include('livewire.invoices.partials._header-fields', [
                        'mode' => 'edit',
                        'contacts' => $contacts,
                        'sequences' => $sequences,
                        'invoice' => $invoice,
                        'isReadOnly' => $isReadOnly,
                    ])
                </x-card>

                {{-- Notes / Causale --}}
                <div @class(['pointer-events-none' => $isReadOnly])>
                    <x-textarea :label="__('app.invoices.notes_label')" wire:model="notes" rows="2" />
                </div>

                @include('livewire.invoices.partials._invoice-lines-editor', [
                    'lines' => $lines,
                    'vatRates' => $vatRates,
                    'isReadOnly' => $isReadOnly,
                ])


            </div>

            {{-- RIGHT COLUMN: Sticky sidebar --}}
            <div class="lg:col-span-1">
                <div class="lg:sticky lg:top-4 space-y-4">

                    {{-- Status badge --}}
                    <div class="flex items-center gap-2">
                        @if($invoice->sdi_status)
                            <x-badge :value="$invoice->sdi_status->label()" variant="$invoice->sdi_status->badgeVariant()" type="soft"" />
                        @else
                            <x-badge :value="$invoice->status->label()" variant="$invoice->status->badgeVariant()" type="soft"" />
                        @endif
                    </div>

                    @include('livewire.invoices.partials._tax-options-section', [
                        'vatRates' => $vatRates,
                        'isReadOnly' => $isReadOnly,
                    ])

                    @include('livewire.invoices.partials._payment-details-section', [
                        'showDueDate' => true,
                        'isReadOnly' => $isReadOnly,
                    ])

                    @include('livewire.invoices.partials._totals-sidebar')

                    {{-- Workflow step: validate XML OR send to SDI (same slot, never both visible) --}}
                    @if(!$isReadOnly && $invoice->isSdiEditable() && $invoice->status->canValidateXml())
                        <x-button :label="__('app.invoices.validate_xml')" wire:click="validateXml" wire:confirm="{{ __('app.invoices.confirm_validate_xml') }}" icon="o-shield-check" variant="accent" class="w-full" spinner="validateXml" :disabled="!$sdiConfigured" />
                    @endif
                    @if(!$isReadOnly && $invoice->isSdiEditable() && $invoice->status->canSendToSdi())
                        <x-button :label="__('app.invoices.send_to_sdi')" wire:click="sendToSdi" wire:confirm="{{ __('app.invoices.confirm_send_sdi') }}" icon="o-paper-airplane" variant="warning" class="w-full" spinner="sendToSdi" :disabled="!$sdiConfigured" />
                    @endif
                    @if(!$sdiConfigured && !$isReadOnly && $invoice->isSdiEditable())
                        <p class="text-xs text-base-content/50 text-center">{{ __('app.invoices.sdi_not_configured_hint') }}</p>
                    @endif

                    {{-- Document actions --}}
                    <div class="flex items-center gap-1 pt-1">
                        <x-button :label="__('app.invoices.download_pdf')" wire:click="downloadPdf" icon="o-document-text" variant="ghost" size="sm" spinner="downloadPdf" />
                        <x-button :label="__('app.invoices.download_xml')" wire:click="downloadXml" icon="o-arrow-down-tray" variant="ghost" size="sm" spinner="downloadXml" />
                        @if($invoice->contact?->email)
                            @allowed('send-document-email')
                                <x-button :label="__('app.email.send_email')" wire:click="openEmailModal" icon="o-envelope" variant="ghost" size="sm" spinner="openEmailModal" />
                            @endallowed
                        @endif
                    </div>

                    {{-- Cancel --}}
                    <div class="text-center pt-2">
                        <x-button :label="__('app.common.cancel')" link="{{ route('sell-invoices.index') }}" icon="o-x-mark" variant="ghost" size="sm" />
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
            </form>
        </x-tabs.panel>

        <x-tabs.panel name="history">
            @livewire('invoices.invoice-timeline', ['invoice' => $invoice], key('timeline-'.$invoice->id))
        </x-tabs.panel>
    </x-tabs>

    {{-- Payment modal --}}
    <x-payment-modal />

    @include('livewire.invoices.partials._reverse-calc-modal', [
        'vatRates' => $vatRates,
    ])

    {{-- Email send modal --}}
    <x-modal wire:model="emailModal" :title="__('app.email.send_email')">
        <div class="space-y-4">
            <x-input :label="__('app.email.recipient')" wire:model="emailRecipient" type="email" />
            <x-input :label="__('app.email.cc')" wire:model="emailCc" type="email" />
            <x-input :label="__('app.email.subject')" wire:model="emailSubject" />
            <x-textarea :label="__('app.email.body')" wire:model="emailBody" rows="8" />
            <p class="text-xs text-base-content/40">{{ __('app.email.placeholders_hint') }}</p>
            <x-toggle :label="__('app.email.attach_pdf')" wire:model="emailAttachPdf" />
        </div>
        <x-slot:actions>
            <x-button :label="__('app.common.cancel')" @click="$wire.emailModal = false" />
            <x-button :label="__('app.email.send')" wire:click="sendEmail" icon="o-envelope" variant="primary" spinner="sendEmail" />
        </x-slot:actions>
    </x-modal>
</div>
