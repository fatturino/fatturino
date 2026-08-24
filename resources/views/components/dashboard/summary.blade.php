@props(['items'])

<div class="grid divide-y divide-border-light overflow-hidden rounded-xl border border-border-light bg-white shadow-[var(--shadow-card)] sm:grid-cols-3 sm:divide-x sm:divide-y-0">
    @foreach($items as $item)
        <x-app-link :href="$item['href']" @class(['group block p-5 transition hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary/20', 'bg-danger-bg/40' => ($item['tone'] ?? 'default') === 'danger'])>
            <p @class(['text-sm font-medium', 'text-danger' => ($item['tone'] ?? 'default') === 'danger', 'text-content' => ($item['tone'] ?? 'default') !== 'danger'])>{{ $item['label'] }}</p>
            <p @class(['mt-2 text-2xl font-bold tabular-nums', 'text-danger' => ($item['tone'] ?? 'default') === 'danger', 'text-content' => ($item['tone'] ?? 'default') !== 'danger'])>{{ $item['value'] }}</p>
            <p class="mt-2 text-xs text-content-muted">{{ $item['detail'] }}</p>
            <p class="mt-0.5 text-xs text-content-muted">{{ $item['period'] }}</p>
        </x-app-link>
    @endforeach
</div>