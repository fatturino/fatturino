@props(['invoices'])

<article class="dashboard-module p-2">
    <div class="dashboard-module-inner p-5">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="font-semibold text-content">Prossime scadenze</h2>
                <p class="mt-1 text-sm text-content-muted">Per data di pagamento prevista</p>
            </div>
            <x-app-link href="/sell-invoices?payment=open" class="-m-2 inline-flex min-h-11 shrink-0 items-center p-2 text-sm font-semibold text-primary underline-offset-4 hover:underline">Vedi aperte</x-app-link>
        </div>

        <div class="mt-5 space-y-2">
            @forelse($invoices as $invoice)
                <x-dashboard.due-date-row :invoice="$invoice" />
            @empty
                <div class="due-date-empty">
                    <p class="font-semibold text-content">Nessuna scadenza aperta</p>
                    <p class="mt-1 text-sm leading-6 text-content-muted">Non ci sono pagamenti previsti nel periodo selezionato.</p>
                </div>
            @endforelse
        </div>
    </div>
</article>
