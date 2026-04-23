<div>
    <x-header :title="__('app.proforma.edit_title', ['number' => $proformaInvoice->number])" separator>
        <x-slot:actions>
            <x-button :label="__('app.proforma.payment_section')" wire:click="openPaymentModal" icon="o-credit-card" class="btn-outline btn-sm" />
            @unless($isReadOnly)
                <x-button :label="__('app.proforma.reverse_calc_title')" wire:click="openReverseCalcModal" icon="o-calculator" class="btn-outline btn-sm" />
            @endunless
        </x-slot:actions>
    </x-header>

    {{-- Status banners --}}
    @if($proformaInvoice->status === \App\Enums\ProformaStatus::Converted)
        <x-alert icon="o-document-check" class="mb-4 alert-success">
            {{ __('app.proforma.converted_banner') }}
            @if($convertedInvoice)
                — <a href="{{ route('sell-invoices.edit', $convertedInvoice) }}" class="link font-semibold" wire:navigate>#{{ $convertedInvoice->number }}</a>
            @endif
        </x-alert>
    @elseif($proformaInvoice->status === \App\Enums\ProformaStatus::Cancelled)
        <x-alert
            :title="__('app.proforma.cancelled_banner')"
            icon="o-x-circle"
            class="mb-4 alert-neutral"
        />
    @elseif($isReadOnly)
        <x-alert
            :title="__('app.proforma.readonly_banner', ['year' => $proformaInvoice->date->year])"
            icon="o-lock-closed"
            class="mb-4 alert-warning"
        />
    @endif

    <form wire:submit="save">
        <div class="grid lg:grid-cols-3 gap-6">

            {{-- LEFT COLUMN: Header fields + Lines --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Header fields --}}
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <x-select :label="__('app.proforma.sequence')" :options="$sequences" wire:model.live="sequence_id" :disabled="$isReadOnly" />
                    <x-input :label="__('app.proforma.number')" wire:model="number" :disabled="$isReadOnly" />
                    <x-datetime :label="__('app.proforma.date')" wire:model="date" type="date" :disabled="$isReadOnly" />
                    <x-select :label="__('app.proforma.customer')" :options="$contacts" wire:model.live="contact_id" search :disabled="$isReadOnly" />
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

                    {{-- Status badge --}}
                    <div class="flex items-center gap-2">
                        <x-badge :value="$proformaInvoice->status->label()" :class="$proformaInvoice->status->color()" />
                    </div>

                    @include('livewire.invoices.partials._tax-options-section', [
                        'vatRates'       => $vatRates,
                        'showVatOptions' => false,
                        'isReadOnly'     => $isReadOnly,
                    ])

                    @include('livewire.invoices.partials._totals-sidebar')

                    {{-- Action buttons --}}
                    <div class="flex flex-col gap-2">
                        @unless($isReadOnly)
                            <x-button :label="__('app.common.save')" wire:click="save" icon="o-check" class="btn-primary w-full" spinner="save" />
                        @endunless

                        {{-- Proforma-specific actions --}}
                        @if($proformaInvoice->status === \App\Enums\ProformaStatus::Draft)
                            <x-button
                                :label="__('app.proforma.mark_as_sent')"
                                wire:click="markAsSent"
                                wire:confirm="{{ __('app.proforma.confirm_mark_sent') }}"
                                icon="o-paper-airplane"
                                class="btn-info w-full"
                                spinner="markAsSent"
                            />
                        @endif

                        @if($proformaInvoice->isConvertible())
                            <x-button
                                :label="__('app.proforma.convert_to_invoice')"
                                wire:click="convertToInvoice"
                                wire:confirm="{{ __('app.proforma.confirm_convert') }}"
                                icon="o-document-check"
                                class="btn-success w-full"
                                spinner="convertToInvoice"
                            />
                        @endif

                        @if(in_array($proformaInvoice->status, [\App\Enums\ProformaStatus::Draft, \App\Enums\ProformaStatus::Sent]))
                            <x-button
                                :label="__('app.proforma.cancel_proforma')"
                                wire:click="cancelProforma"
                                wire:confirm="{{ __('app.proforma.confirm_cancel') }}"
                                icon="o-x-circle"
                                class="btn-ghost w-full text-error"
                                spinner="cancelProforma"
                            />
                        @endif

                        <x-button :label="__('app.invoices.download_pdf')" wire:click="downloadPdf" icon="o-document-text" class="btn-ghost w-full" spinner="downloadPdf" />

                        <x-button :label="__('app.common.cancel')" link="{{ route('proforma.index') }}" icon="o-x-mark" class="btn-ghost w-full" />

                        {{-- Email send button (shown when contact has email and email sending is allowed) --}}
                        @if($proformaInvoice->contact?->email)
                            @allowed('send-document-email')
                                <x-button
                                    :label="__('app.email.send_email')"
                                    wire:click="openEmailModal"
                                    icon="o-envelope"
                                    class="btn-outline w-full"
                                    spinner="openEmailModal"
                                />
                            @endallowed
                        @endif
                    </div>

                </div>
            </div>
        </div>
    </form>

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
            <x-button :label="__('app.email.send')" wire:click="sendEmail" icon="o-envelope" class="btn-primary" spinner="sendEmail" />
        </x-slot:actions>
    </x-modal>
</div>
