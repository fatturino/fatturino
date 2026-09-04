@props(['items'])

<div class="grid gap-3 sm:grid-cols-3 lg:gap-4">
    @foreach($items as $item)
        <x-app-link :href="$item['href']" @class(['dashboard-kpi group block p-2 focus:outline-none focus:ring-2 focus:ring-primary/30', 'dashboard-kpi-danger' => ($item['tone'] ?? 'default') === 'danger'])>
            <span class="dashboard-kpi-inner block p-4 sm:p-5">
                <span @class(['block text-sm font-semibold', 'text-danger' => ($item['tone'] ?? 'default') === 'danger', 'text-content' => ($item['tone'] ?? 'default') !== 'danger'])>{{ $item['label'] }}</span>
                <span @class(['mt-3 block text-2xl font-bold tracking-tight tabular-nums sm:text-3xl', 'text-danger' => ($item['tone'] ?? 'default') === 'danger', 'text-content' => ($item['tone'] ?? 'default') !== 'danger'])>{{ $item['value'] }}</span>
                <span class="mt-3 block text-xs leading-5 text-content-muted">{{ $item['detail'] }}<br>{{ $item['period'] }}</span>
            </span>
        </x-app-link>
    @endforeach
</div>
