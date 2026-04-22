<div>
    <x-header :title="__('app.invoices.edit_title', ['number' => $invoice->number])" separator>
        <x-slot:actions>
            <x-button :label="__('app.invoices.payment_section')" wire:click="openPaymentModal" icon="o-credit-card" class="btn-outline btn-sm" />
            @unless($isReadOnly)
                <x-button :label="__('app.invoices.reverse_calc_title')" wire:click="openReverseCalcModal" icon="o-calculator" class="btn-outline btn-sm" />
            @endunless
        </x-slot:actions>
    </x-header>

    {{-- Read-only banner --}}
    @if($isSdiLocked)
        <x-alert
            :title="__('app.invoices.sdi_locked_banner')"
            icon="o-lock-closed"
            class="mb-4 alert-info"
        />
    @elseif($isReadOnly)
        <x-alert
            :title="__('app.invoices.readonly_banner', ['year' => $invoice->date->year])"
            icon="o-lock-closed"
            class="mb-4 alert-warning"
        />
    @endif

    <x-tabs wire:model="activeTab">
    <x-tab name="details" :label="__('app.audit.tab_details')" icon="o-document-text">
    <form wire:submit="save">
        <div class="grid lg:grid-cols-3 gap-6">

            {{-- LEFT COLUMN: Header fields + Invoice lines --}}
            <div class="lg:col-span-2 space-y-6">

                @include('livewire.invoices.partials._header-fields', [
                    'mode' => 'edit',
                    'contacts' => $contacts,
                    'sequences' => $sequences,
                    'invoice' => $invoice,
                    'isReadOnly' => $isReadOnly,
                ])

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
                            <x-badge :value="$invoice->sdi_status->label()" :class="$invoice->sdi_status->color()" />
                        @else
                            <x-badge :value="$invoice->status->label()" :class="$invoice->status->color()" />
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

                    {{-- Action buttons --}}
                    <div class="flex flex-col gap-2">
                        {{-- Primary actions --}}
                        @unless($isReadOnly)
                            <x-button :label="__('app.common.save')" wire:click="save" icon="o-check" class="btn-primary w-full" spinner="save" />
                        @endunless

                        {{-- SDI actions --}}
                        @if(!$isReadOnly && $invoice->isSdiEditable() && $invoice->status->canValidateXml())
                            <x-button :label="__('app.invoices.validate_xml')" wire:click="validateXml" wire:confirm="{{ __('app.invoices.confirm_validate_xml') }}" icon="o-shield-check" class="btn-accent w-full" spinner="validateXml" :disabled="!$sdiConfigured" />
                        @endif
                        @if(!$isReadOnly && $invoice->isSdiEditable() && $invoice->status->canSendToSdi())
                            <x-button :label="__('app.invoices.send_to_sdi')" wire:click="sendToSdi" wire:confirm="{{ __('app.invoices.confirm_send_sdi') }}" icon="o-paper-airplane" class="btn-warning w-full" spinner="sendToSdi" :disabled="!$sdiConfigured" />
                        @endif
                        @if(!$sdiConfigured && !$isReadOnly && $invoice->isSdiEditable())
                            <p class="text-xs text-base-content/50 text-center">{{ __('app.invoices.sdi_not_configured_hint') }}</p>
                        @endif

                        {{-- Secondary actions: downloads + email + cancel in a 2-column grid --}}
                        <div class="grid grid-cols-2 gap-2 pt-1">
                            <x-button :label="__('app.invoices.download_pdf')" wire:click="downloadPdf" icon="o-document-text" class="btn-ghost btn-sm" spinner="downloadPdf" />
                            <x-button :label="__('app.invoices.download_xml')" wire:click="downloadXml" icon="o-arrow-down-tray" class="btn-ghost btn-sm" spinner="downloadXml" />
                            @if($invoice->contact?->email)
                                <x-button :label="__('app.email.send_email')" wire:click="openEmailModal" icon="o-envelope" class="btn-ghost btn-sm" spinner="openEmailModal" />
                            @endif
                            <x-button :label="__('app.common.cancel')" link="{{ route('sell-invoices.index') }}" icon="o-x-mark" class="btn-ghost btn-sm" />
                        </div>
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
    </x-tab>
    <x-tab name="history" :label="__('app.audit.tab_history')" icon="o-clock">
        @livewire('invoices.invoice-timeline', ['invoice' => $invoice], key('timeline-'.$invoice->id))
    </x-tab>
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
            <x-input :label="__('app.email.subject')" wire:model="emailSubject" />
            <x-textarea :label="__('app.email.body')" wire:model="emailBody" rows="8" />
            <p class="text-xs text-base-content/40">{{ __('app.email.placeholders_hint') }}</p>
            <x-toggle :label="__('app.email.attach_pdf')" wire:model="emailAttachPdf" />
        </div>
        <x-slot:actions>
            <x-button :label="__('app.common.cancel')" @click="$wire.emailModal = false" />
            <x-button :label="__('app.email.send')" wire:click="sendEmail" icon="o-envelope" class="btn-primary" spinner="sendEmail" />
        </x-slot:actions>
    </x-modal>
</div>
