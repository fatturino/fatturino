@props(['invoice'])

<x-app-link
    :href="route('sell-invoices.edit', $invoice['id'])"
    @class(['due-date-row due-date-card group block', 'due-date-row-'.$invoice['due_tone']])
>
    <span @class(['due-date-marker', "due-date-marker-{$invoice['due_tone']}"]) aria-hidden="true">
        <span class="text-sm font-bold tabular-nums">{{ $invoice['days_until_due'] === null ? 'N/D' : abs($invoice['days_until_due']) }}</span>
        <span class="text-[0.625rem] font-bold uppercase tracking-wide">{{ $invoice['days_until_due'] === null ? '' : 'gg' }}</span>
    </span>
    <span class="min-w-0 flex-1">
        <span class="flex flex-wrap items-center gap-x-2 gap-y-1">
            <span class="min-w-0 break-words text-sm font-semibold text-content">{{ $invoice['contact'] ?? 'Cliente non associato' }}</span>
            <x-badge :value="$invoice['due_label']" :variant="$invoice['due_tone'] === 'default' ? 'neutral' : $invoice['due_tone']" />
        </span>
        <span class="mt-1 block text-xs leading-5 text-content-muted">
            {{ $invoice['due_detail'] }} <span aria-hidden="true">·</span> {{ $invoice['due_date'] ?? 'Data non disponibile' }}
        </span>
    </span>
    <span class="due-date-amount shrink-0 text-right">
        <span class="block text-sm font-semibold tabular-nums text-content">{{ '€ '.number_format($invoice['remaining_balance'] / 100, 2, ',', '.') }}</span>
        <span class="mt-1 block text-xs font-semibold text-primary">Apri fattura</span>
    </span>
</x-app-link>
