@props([
    'netTotal',
    'vatTotal',
    'grossTotal' => null,
    'netDue' => null,
    'fundAmount' => 0,
    'fundPercent' => null,
    'stampDutyAmount' => 0,
    'stampDutyLabel' => 'Marca da bollo',
    'withholdingAmount' => 0,
    'withholdingPercent' => null,
    'splitPaymentAmount' => 0,
    'note' => null,
])

<article class="sticky top-20 rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]">
    <h2 class="font-bold">Riepilogo</h2>
    <dl class="mt-4 space-y-3 text-sm">
        <div class="flex justify-between"><dt>Totale netto</dt><dd class="font-bold tabular-nums">€ {{ number_format($netTotal, 2, ',', '.') }}</dd></div>
        @if($fundAmount > 0)<div class="flex justify-between"><dt>Cassa previdenziale{{ $fundPercent ? ' ('.$fundPercent.'%)' : '' }}</dt><dd class="font-bold tabular-nums">€ {{ number_format($fundAmount, 2, ',', '.') }}</dd></div>@endif
        <div class="flex justify-between"><dt>IVA</dt><dd class="font-bold tabular-nums">€ {{ number_format($vatTotal, 2, ',', '.') }}</dd></div>
        @if($stampDutyAmount > 0)<div class="flex justify-between"><dt>{{ $stampDutyLabel }}</dt><dd class="font-bold tabular-nums">€ {{ number_format($stampDutyAmount, 2, ',', '.') }}</dd></div>@endif
        @if($netDue !== null)
            <div class="flex justify-between border-t border-border-light pt-3 text-base"><dt class="font-bold">Totale documento</dt><dd class="font-bold tabular-nums">€ {{ number_format($grossTotal ?? ($netTotal + $vatTotal + $fundAmount + $stampDutyAmount), 2, ',', '.') }}</dd></div>
            @if($withholdingAmount > 0)<div class="flex justify-between text-content-muted"><dt>Ritenuta d'acconto{{ $withholdingPercent ? ' ('.$withholdingPercent.'%)' : '' }}</dt><dd class="tabular-nums">-€ {{ number_format($withholdingAmount, 2, ',', '.') }}</dd></div>@endif
            @if($splitPaymentAmount > 0)<div class="flex justify-between text-content-muted"><dt>Split payment IVA</dt><dd class="tabular-nums">-€ {{ number_format($splitPaymentAmount, 2, ',', '.') }}</dd></div>@endif
            <div class="flex justify-between pt-1 text-base"><dt class="font-bold">Totale da pagare</dt><dd class="font-bold tabular-nums">€ {{ number_format($netDue, 2, ',', '.') }}</dd></div>
        @else
            <div class="flex justify-between border-t border-border-light pt-3 text-base"><dt class="font-bold">Totale</dt><dd class="font-bold tabular-nums">€ {{ number_format($grossTotal ?? ($netTotal + $vatTotal), 2, ',', '.') }}</dd></div>
        @endif
    </dl>
    @if($note)<p class="mt-4 text-xs text-content-muted">{{ $note }}</p>@endif
</article>
