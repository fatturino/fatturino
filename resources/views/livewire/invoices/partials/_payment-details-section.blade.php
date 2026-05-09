{{--
    Collapsible "Dettagli pagamento" sidebar section: payment terms, method,
    optional due date, and bank details when a payment method is selected.

    Shows a summary when collapsed so the user can confirm defaults at a glance.

    Required vars (bound from host component state):
      $payment_method, $payment_terms, $bank_name, $bank_iban
    Optional vars:
      $showDueDate : bool  (default false — only the edit view shows due date)
      $due_date    : string (when $showDueDate = true)
      $isReadOnly  : bool  (default false)
--}}
@php
    $isReadOnly  = $isReadOnly  ?? false;
    $showDueDate = $showDueDate ?? false;

    // Build summary of payment details for the collapsed state
    $summaryParts = [];
    if ($payment_terms) {
        $termsLabels = \App\Enums\PaymentTerms::options();
        $summaryParts[] = $termsLabels[$payment_terms] ?? $payment_terms;
    }
    if ($payment_method) {
        $methodLabels = \App\Enums\PaymentMethod::options();
        $summaryParts[] = $methodLabels[$payment_method] ?? $payment_method;
    }
    $hasDetails = !empty($summaryParts);
@endphp

<div x-data="{ open: false }" class="bg-base-100 rounded-xl border border-base-200">
    <button type="button" @click="open = !open" class="flex items-center justify-between w-full p-4 cursor-pointer">
        <div class="flex-1 min-w-0">
            <span class="text-sm font-medium">{{ __('app.invoices.payment_details_section') }}</span>
            {{-- Summary visible when collapsed --}}
            <div x-show="!open" class="mt-1.5">
                @if($hasDetails)
                    <span class="text-xs text-base-content/60">{{ implode(' · ', $summaryParts) }}</span>
                @else
                    <span class="text-xs text-base-content/40">{{ __('app.invoices.no_payment_details') }}</span>
                @endif
            </div>
        </div>
        <x-icon name="o-chevron-down" class="w-4 h-4 shrink-0 transition-transform duration-200 ml-2" ::class="open && 'rotate-180'" />
    </button>
    <div x-show="open" x-collapse>
        <div @class(['px-4 pb-4 space-y-3', 'pointer-events-none' => $isReadOnly])>
            <x-select :label="__('app.invoices.payment_terms_label')" :options="\App\Enums\PaymentTerms::options()" wire:model="payment_terms" :placeholder="__('app.common.select')" />
            <x-select :label="__('app.invoices.payment_method_label')" :options="\App\Enums\PaymentMethod::options()" wire:model="payment_method" :placeholder="__('app.common.select')" />
            @if($showDueDate)
                <x-input type="date" :label="__('app.invoices.due_date')" wire:model="due_date" />
            @endif
            @if($payment_method)
                <x-input :label="__('app.invoices.bank_name_label')" wire:model="bank_name" />
                <x-input :label="__('app.invoices.bank_iban_label')" wire:model="bank_iban" />
            @endif
        </div>
    </div>
</div>
