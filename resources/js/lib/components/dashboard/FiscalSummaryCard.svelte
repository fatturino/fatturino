<script>
    import { formatCurrency } from './formatters.js'

    let {
        vatCollectedYtd = 0,
        vatOnPurchasesYtd = 0,
        vatBalanceYtd = 0,
        withholdingTaxYtd = 0,
        vatByQuarter = [],
    } = $props()

    const currentQuarter = Math.floor(new Date().getMonth() / 3) + 1
    function quarterRow(q) {
        return vatByQuarter.find(row => Number(row.q) === q) ?? { q, collected: 0, purchases: 0, balance: 0 }
    }
</script>

<div class="card-brand p-5">
    <h3 class="text-base font-semibold text-brand-deep mb-3">Riepilogo fiscale</h3>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        <div class="rounded-lg bg-brand/5 p-3">
            <p class="text-[11px] text-brand-secondary/60 mb-1">IVA incassata</p>
            <p class="text-base font-bold text-brand-deep">{formatCurrency(vatCollectedYtd)}</p>
        </div>
        <div class="rounded-lg bg-brand/5 p-3">
            <p class="text-[11px] text-brand-secondary/60 mb-1">IVA acquisti</p>
            <p class="text-base font-bold text-brand-deep">{formatCurrency(vatOnPurchasesYtd)}</p>
        </div>
        <div class="rounded-lg bg-brand/5 p-3">
            <p class="text-[11px] text-brand-secondary/60 mb-1">Saldo IVA</p>
            <p class="text-base font-bold {vatBalanceYtd >= 0 ? 'text-red-600' : 'text-green-600'}">{formatCurrency(vatBalanceYtd)}</p>
        </div>
        <div class="rounded-lg bg-brand/5 p-3">
            <p class="text-[11px] text-brand-secondary/60 mb-1">Ritenute YTD</p>
            <p class="text-base font-bold text-brand-deep">{formatCurrency(withholdingTaxYtd)}</p>
        </div>
    </div>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-2">
        {#each [1, 2, 3, 4] as q}
            {@const row = quarterRow(q)}
            <div class="rounded-lg border border-border-light px-3 py-2 {q === currentQuarter ? 'bg-brand/5' : ''}">
                <p class="text-[11px] text-brand-secondary/60">Q{q}</p>
                <p class="text-xs mt-0.5 text-brand-secondary/80">IVA {formatCurrency(row.collected)}</p>
                <p class="text-xs text-brand-secondary/80">Acq {formatCurrency(row.purchases)}</p>
                <p class="text-xs font-semibold {row.balance >= 0 ? 'text-red-600' : 'text-green-600'}">Saldo {formatCurrency(row.balance)}</p>
            </div>
        {/each}
    </div>
</div>

