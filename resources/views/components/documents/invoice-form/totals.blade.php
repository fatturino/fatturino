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
    'variant' => 'default',
])

<article @class([
    'sticky top-20 rounded-xl border bg-white p-5',
    'border-border-light shadow-[var(--shadow-card)]' => $variant !== 'editor',
    'border-border' => $variant === 'editor',
])>
    <h2 @class(['text-content', 'font-semibold' => $variant === 'editor', 'font-bold' => $variant !== 'editor'])>Riepilogo</h2>
    <dl class="mt-4 space-y-3 text-sm">
        <div class="flex justify-between"><dt>Totale netto</dt><dd @class(['tabular-nums', 'font-medium' => $variant === 'editor', 'font-bold' => $variant !== 'editor'])>€ {{ number_format($netTotal, 2, ',', '.') }}</dd></div>
        @if($fundAmount > 0)<div class="flex justify-between"><dt>Cassa previdenziale{{ $fundPercent ? ' ('.$fundPercent.'%)' : '' }}</dt><dd @class(['tabular-nums', 'font-medium' => $variant === 'editor', 'font-bold' => $variant !== 'editor'])>€ {{ number_format($fundAmount, 2, ',', '.') }}</dd></div>@endif
        <div class="flex justify-between"><dt>IVA</dt><dd @class(['tabular-nums', 'font-medium' => $variant === 'editor', 'font-bold' => $variant !== 'editor'])>€ {{ number_format($vatTotal, 2, ',', '.') }}</dd></div>
        @if($stampDutyAmount > 0)<div class="flex justify-between"><dt>{{ $stampDutyLabel }}</dt><dd @class(['tabular-nums', 'font-medium' => $variant === 'editor', 'font-bold' => $variant !== 'editor'])>€ {{ number_format($stampDutyAmount, 2, ',', '.') }}</dd></div>@endif
        @if($netDue !== null)
            <div @class(['flex justify-between border-t pt-3 text-base', 'border-border' => $variant === 'editor', 'border-border-light' => $variant !== 'editor'])><dt @class(['font-semibold' => $variant === 'editor', 'font-bold' => $variant !== 'editor'])>Totale documento</dt><dd @class(['tabular-nums', 'font-semibold' => $variant === 'editor', 'font-bold' => $variant !== 'editor'])>€ {{ number_format($grossTotal ?? ($netTotal + $vatTotal + $fundAmount + $stampDutyAmount), 2, ',', '.') }}</dd></div>
            @if($withholdingAmount > 0)<div class="flex justify-between text-content-muted"><dt>Ritenuta d'acconto{{ $withholdingPercent ? ' ('.$withholdingPercent.'%)' : '' }}</dt><dd class="tabular-nums">-€ {{ number_format($withholdingAmount, 2, ',', '.') }}</dd></div>@endif
            @if($splitPaymentAmount > 0)<div class="flex justify-between text-content-muted"><dt>Split payment IVA</dt><dd class="tabular-nums">-€ {{ number_format($splitPaymentAmount, 2, ',', '.') }}</dd></div>@endif
            <div class="flex justify-between pt-1 text-base"><dt @class(['font-semibold' => $variant === 'editor', 'font-bold' => $variant !== 'editor'])>Totale da pagare</dt><dd @class(['tabular-nums', 'font-semibold' => $variant === 'editor', 'font-bold' => $variant !== 'editor'])>€ {{ number_format($netDue, 2, ',', '.') }}</dd></div>
        @else
            <div @class(['flex justify-between border-t pt-3 text-base', 'border-border' => $variant === 'editor', 'border-border-light' => $variant !== 'editor'])><dt @class(['font-semibold' => $variant === 'editor', 'font-bold' => $variant !== 'editor'])>Totale</dt><dd @class(['tabular-nums', 'font-semibold' => $variant === 'editor', 'font-bold' => $variant !== 'editor'])>€ {{ number_format($grossTotal ?? ($netTotal + $vatTotal), 2, ',', '.') }}</dd></div>
        @endif
    </dl>
    @if($note)<p class="mt-4 text-xs text-content-muted">{{ $note }}</p>@endif
</article>
