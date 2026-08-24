@props(['invoices'])

<article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]">
    <div class="flex items-start justify-between gap-4"><div><h2 class="font-semibold text-content">Documenti recenti</h2><p class="mt-1 text-sm text-content-muted">Le ultime fatture emesse nell’anno fiscale selezionato.</p></div><x-app-link :href="route('sell-invoices.index')" class="shrink-0 text-sm font-medium text-primary hover:underline">Vedi tutte</x-app-link></div>
    <div class="mt-4 overflow-x-auto">
        <table class="w-full min-w-[40rem] text-left text-sm">
            <thead class="border-b border-border-light text-xs text-content-muted"><tr><th scope="col" class="px-3 py-2 font-medium">Documento</th><th scope="col" class="px-3 py-2 font-medium">Cliente</th><th scope="col" class="px-3 py-2 font-medium">Data</th><th scope="col" class="px-3 py-2 text-right font-medium">Netto</th><th scope="col" class="px-3 py-2 text-right font-medium">Stato incasso</th></tr></thead>
            <tbody class="divide-y divide-border-light">
                @forelse($invoices as $invoice)
                    <tr class="transition hover:bg-surface-muted"><td class="px-3 py-3"><x-app-link :href="route('sell-invoices.edit', $invoice['id'])" class="font-medium text-primary hover:underline">{{ $invoice['number'] ?? 'Senza numero' }}</x-app-link></td><td class="px-3 py-3 text-content">{{ $invoice['contact'] ?? 'Cliente non associato' }}</td><td class="px-3 py-3 text-content-muted">{{ $invoice['date'] }}</td><td class="px-3 py-3 text-right tabular-nums"><p class="font-medium text-content">{{ '€ '.number_format($invoice['total_net'] / 100, 2, ',', '.') }}</p><p class="mt-0.5 text-xs text-content-muted">IVA {{ '€ '.number_format($invoice['total_vat'] / 100, 2, ',', '.') }}</p></td><td class="px-3 py-3 text-right"><span @class(['inline-flex items-center gap-1.5 text-xs font-medium', 'text-success' => $invoice['payment_status'] === 'paid', 'text-danger' => $invoice['payment_status'] === 'overdue', 'text-warning' => in_array($invoice['payment_status'], ['unpaid', 'partial'], true)])><span class="size-1.5 rounded-full bg-current"></span>{{ match($invoice['payment_status']) { 'paid' => 'Pagata', 'overdue' => 'Scaduta', 'partial' => 'Parziale', default => 'Da incassare' } }}</span></td></tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-10 text-center text-sm text-content-muted">Nessun documento emesso nel periodo.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</article>