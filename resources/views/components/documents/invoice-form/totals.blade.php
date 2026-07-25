@props([
    'netTotal',
    'vatTotal',
    'grossTotal' => null,
    'netDue' => null,
    'fundAmount' => 0,
    'stampDutyAmount' => 0,
    'stampDutyLabel' => 'Marca da bollo',
    'note' => null,
])

<article class="sticky top-20 rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]">
    <h2 class="font-bold">Riepilogo</h2>
    <dl class="mt-4 space-y-3 text-sm">
        <div class="flex justify-between"><dt>Totale netto</dt><dd class="font-bold tabular-nums">€ {{ number_format($netTotal, 2, ',', '.') }}</dd></div>
        @if($fundAmount > 0)<div class="flex justify-between"><dt>Cassa previdenziale</dt><dd class="font-bold tabular-nums">€ {{ number_format($fundAmount, 2, ',', '.') }}</dd></div>@endif
        <div class="flex justify-between"><dt>IVA</dt><dd class="font-bold tabular-nums">€ {{ number_format($vatTotal, 2, ',', '.') }}</dd></div>
        @if($stampDutyAmount > 0)<div class="flex justify-between"><dt>{{ $stampDutyLabel }}</dt><dd class="font-bold tabular-nums">€ {{ number_format($stampDutyAmount, 2, ',', '.') }}</dd></div>@endif
        <div class="flex justify-between border-t border-border-light pt-3 text-base"><dt class="font-bold">{{ $netDue === null ? 'Totale' : 'Totale da pagare' }}</dt><dd class="font-bold tabular-nums">€ {{ number_format($netDue ?? $grossTotal ?? ($netTotal + $vatTotal), 2, ',', '.') }}</dd></div>
    </dl>
    @if($note)<p class="mt-4 text-xs text-content-muted">{{ $note }}</p>@endif
</article>
