{{--
    Payment drawer (slides from right, keeps invoice context visible).
    Requires the host Livewire component to use the HasPaymentTracking trait.
--}}
<x-drawer wire:model="paymentModal" :title="__('app.payments.title')" right with-close-button separator class="max-w-xl">
    <div class="space-y-6">

        {{-- Registered payments list --}}
        <div>
            <div class="text-sm font-semibold mb-2">{{ __('app.payments.registered_payments') }}</div>

            @if($this->invoicePayments->isEmpty())
                <p class="text-sm text-base-content/50">{{ __('app.payments.no_payments') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-base-200 text-sm">
                        <thead>
                            <tr>
                                <th>{{ __('app.payments.date') }}</th>
                                <th>{{ __('app.payments.amount') }}</th>
                                <th>{{ __('app.payments.method') }}</th>
                                <th>{{ __('app.payments.reference') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->invoicePayments as $payment)
                                <tr x-data="{ confirming: false }">
                                    <td>{{ $payment->paid_at->format('d/m/Y') }}</td>
                                    <td class="tabular-nums">€ {{ number_format($payment->amount / 100, 2, ',', '.') }}</td>
                                    <td>{{ $payment->payment_method ?? '—' }}</td>
                                    <td class="max-w-xs truncate">{{ $payment->reference ?? '—' }}</td>
                                    <td class="text-right">
                                        {{-- Inline confirm: show trash icon first, then confirm/cancel buttons --}}
                                        <template x-if="!confirming">
                                            <x-button
                                                icon="o-trash"
                                                variant="ghost" size="xs" class="!text-error"
                                                @click="confirming = true"
                                            />
                                        </template>
                                        <template x-if="confirming">
                                            <div class="flex items-center gap-1 justify-end">
                                                <x-button
                                                    :label="__('app.payments.delete_yes')"
                                                    wire:click="deletePayment({{ $payment->id }})"
                                                    variant="danger" size="xs"
                                                    spinner
                                                />
                                                <x-button
                                                    :label="__('app.common.cancel')"
                                                    variant="ghost" size="xs"
                                                    @click="confirming = false"
                                                />
                                            </div>
                                        </template>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Remaining balance --}}
        @php
            $invoice = $this->getPayableInvoice();
            $remaining = $invoice->remainingBalance();
        @endphp
        <div class="flex justify-between items-center text-sm border-t pt-3">
            <span class="font-semibold">{{ __('app.payments.remaining_balance') }}</span>
            <span class="font-bold tabular-nums {{ $remaining > 0 ? 'text-warning' : 'text-success' }}">
                € {{ number_format($remaining / 100, 2, ',', '.') }}
            </span>
        </div>

        {{-- Record payment form --}}
        @if($remaining > 0)
            <div class="border-t pt-4 space-y-4">
                <div class="text-sm font-semibold">{{ __('app.payments.add_payment') }}</div>

                <div class="grid grid-cols-2 gap-4">
                    <x-input
                        :label="__('app.payments.amount')"
                        wire:model="newPaymentAmount"
                        type="number"
                        step="0.01"
                        min="0.01"
                        prefix="€"
                    />
                    <x-datepicker
                        :label="__('app.payments.date')"
                        wire:model="newPaymentDate"
                    />
                </div>

                <x-select
                    :label="__('app.payments.method')"
                    :options="$this->paymentMethodOptions"
                    wire:model="newPaymentMethod"
                    option-value="id"
                    option-label="name"
                    placeholder="{{ __('app.payments.method_optional') }}"
                />

                <x-input
                    :label="__('app.payments.reference')"
                    wire:model="newPaymentReference"
                    :placeholder="__('app.payments.reference_placeholder')"
                />

                <x-textarea
                    :label="__('app.payments.notes')"
                    wire:model="newPaymentNotes"
                    rows="2"
                />
            </div>
        @endif
    </div>

    <x-slot:actions>
        <x-button :label="__('app.common.close')" @click="$wire.paymentModal = false" />
        @if($remaining > 0)
            <x-button
                :label="__('app.payments.mark_as_paid')"
                wire:click="markAsPaid"
                icon="o-check-circle"
                variant="success" size="sm"
                spinner="markAsPaid"
            />
            <x-button
                :label="__('app.payments.record_payment')"
                wire:click="addPayment"
                icon="o-plus"
                variant="primary"
                spinner="addPayment"
            />
        @endif
    </x-slot:actions>
</x-drawer>
