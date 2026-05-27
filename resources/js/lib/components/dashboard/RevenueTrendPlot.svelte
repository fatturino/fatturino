<script>
    import { getContext } from 'svelte'

    let {
        currentTrend = [],
        previousTrend = [],
        labels = [],
    } = $props()

    const { xScale, yScale, width, height } = getContext('LayerCake')

    function chartPoints(series, x, y) {
        return series.map(d => `${x(d.month)},${y(d.value)}`).join(' ')
    }

    function yTicks(scale) {
        return typeof scale?.ticks === 'function' ? scale.ticks(4) : []
    }

    function xTicks(series) {
        return series.map(d => d.month)
    }

    const horizontalTicks = $derived(yTicks($yScale))
    const monthTicks = $derived(xTicks(currentTrend))
</script>

{#each horizontalTicks as tick}
    <line
        x1="0"
        y1={$yScale(tick)}
        x2={$width}
        y2={$yScale(tick)}
        stroke="currentColor"
        class="text-brand-secondary/15"
        stroke-width="1"
    />
{/each}

<polyline
    points={chartPoints(previousTrend, $xScale, $yScale)}
    fill="none"
    stroke="currentColor"
    class="text-brand-secondary/40"
    stroke-width="1.6"
    stroke-dasharray="3 3"
/>
<polyline
    points={chartPoints(currentTrend, $xScale, $yScale)}
    fill="none"
    stroke="currentColor"
    class="text-brand-deep"
    stroke-width="2.2"
/>

{#each monthTicks as month}
    <text
        x={$xScale(month)}
        y={$height + 14}
        text-anchor="middle"
        class="fill-brand-secondary/55 text-[9px]"
    >
        {labels?.[month] ?? ''}
    </text>
{/each}
