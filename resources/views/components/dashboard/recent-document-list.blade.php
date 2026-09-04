@props(['invoices'])

<article class="dashboard-module p-2">
    <div class="dashboard-module-inner p-5">
    <div class="flex items-start justify-between gap-4"><div><h2 class="font-semibold text-content">Documenti recenti</h2><p class="mt-1 text-sm text-content-muted">Le ultime fatture emesse nell’anno fiscale selezionato.</p></div><x-app-link :href="route('sell-invoices.index')" class="shrink-0 text-sm font-semibold text-primary underline-offset-4 hover:underline">Vedi tutte</x-app-link></div>
    <div class="mt-5 space-y-2 sm:hidden">
        @forelse($invoices as $invoice)
            <x-app-link :href="route('sell-invoices.edit', $invoice['id'])" class="block rounded-lg border border-border-light p-4 transition-colors hover:bg-primary-subtle/45 focus:outline-none focus:ring-2 focus:ring-primary/30">
                <div class="flex items-start justify-between gap-3"><span class="font-semibold text-primary">{{ $invoice['number'] ?? 'Senza numero' }}</span><span @class(['inline-flex items-center gap-1.5 text-xs font-semibold', 'text-success' => $invoice['payment_status'] === 'paid', 'text-danger' => $invoice['payment_status'] === 'overdue', 'text-warning' => in_array($invoice['payment_status'], ['unpaid', 'partial'], true)])><span class="size-1.5 rounded-full bg-current"></span>{{ match($invoice['payment_status']) { 'paid' => 'Pagata', 'overdue' => 'Scaduta', 'partial' => 'Parziale', default => 'Da incassare' } }}</span></div>
                <p class="mt-2 truncate text-sm font-medium text-content">{{ $invoice['contact'] ?? 'Cliente non associato' }}</p>
                <div class="mt-3 flex items-end justify-between gap-3 text-xs text-content-muted"><span>{{ $invoice['date'] }}</span><span class="text-right tabular-nums"><span class="block text-sm font-semibold text-content">{{ '€ '.number_format($invoice['total_net'] / 100, 2, ',', '.') }}</span>IVA {{ '€ '.number_format($invoice['total_vat'] / 100, 2, ',', '.') }}</span></div>
            </x-app-link>
        @empty
            <p class="rounded-lg border border-dashed border-border px-3 py-10 text-center text-sm text-content-muted">Nessun documento emesso nel periodo.</p>
        @endforelse
    </div>
    <div class="mt-5 hidden overflow-x-auto rounded-lg border border-border-light sm:block">
        <table class="w-full min-w-[40rem] text-left text-sm">
            <thead class="border-b border-border-light bg-surface-muted text-xs text-content-muted"><tr><th scope="col" class="px-3 py-3 font-semibold">Documento</th><th scope="col" class="px-3 py-3 font-semibold">Cliente</th><th scope="col" class="px-3 py-3 font-semibold">Data</th><th scope="col" class="px-3 py-3 text-right font-semibold">Netto</th><th scope="col" class="px-3 py-3 text-right font-semibold">Stato incasso</th></tr></thead>
            <tbody class="divide-y divide-border-light">
                @forelse($invoices as $invoice)
                    <tr class="transition-colors hover:bg-primary-subtle/45"><td class="px-3 py-3"><x-app-link :href="route('sell-invoices.edit', $invoice['id'])" class="font-semibold text-primary underline-offset-4 hover:underline">{{ $invoice['number'] ?? 'Senza numero' }}</x-app-link></td><td class="px-3 py-3 text-content">{{ $invoice['contact'] ?? 'Cliente non associato' }}</td><td class="px-3 py-3 text-content-muted">{{ $invoice['date'] }}</td><td class="px-3 py-3 text-right tabular-nums"><p class="font-semibold text-content">{{ '€ '.number_format($invoice['total_net'] / 100, 2, ',', '.') }}</p><p class="mt-0.5 text-xs text-content-muted">IVA {{ '€ '.number_format($invoice['total_vat'] / 100, 2, ',', '.') }}</p></td><td class="px-3 py-3 text-right"><span @class(['inline-flex items-center gap-1.5 text-xs font-semibold', 'text-success' => $invoice['payment_status'] === 'paid', 'text-danger' => $invoice['payment_status'] === 'overdue', 'text-warning' => in_array($invoice['payment_status'], ['unpaid', 'partial'], true)])><span class="size-1.5 rounded-full bg-current"></span>{{ match($invoice['payment_status']) { 'paid' => 'Pagata', 'overdue' => 'Scaduta', 'partial' => 'Parziale', default => 'Da incassare' } }}</span></td></tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-10 text-center text-sm text-content-muted">Nessun documento emesso nel periodo.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    </div>
</article>
