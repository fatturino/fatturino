<script>
    import { LayerCake, Svg } from 'layercake'
    import RevenueTrendPlot from './RevenueTrendPlot.svelte'

    let { revenueTrend = [] } = $props()

    const currentTrend = $derived((revenueTrend?.current ?? []).map((value, month) => ({ month, value })))
    const previousTrend = $derived((revenueTrend?.previous ?? []).map((value, month) => ({ month, value })))
    const trendData = $derived([
        ...currentTrend,
        ...previousTrend,
    ])
</script>

<div class="card-brand p-5">
    <h3 class="text-base font-semibold text-brand-deep mb-3">Andamento fatturato</h3>
    {#if revenueTrend?.current?.length > 1}
        <div class="h-44 w-full">
            <LayerCake
                x="month"
                y="value"
                data={trendData}
                padding={{ top: 8, right: 8, bottom: 22, left: 8 }}
            >
                <Svg>
                    <RevenueTrendPlot
                        {currentTrend}
                        {previousTrend}
                        labels={revenueTrend?.labels ?? []}
                    />
                </Svg>
            </LayerCake>
        </div>
    {:else}
        <div class="h-40 flex items-center justify-center text-sm text-brand-secondary/40">
            Dati andamento fatturato non disponibili.
        </div>
    {/if}
</div>
