@props(['recentInvoices', 'isCurrentYear'])

<x-card :title="__('app.dashboard.recent_invoices')">
    <x-slot:menu>
        <x-button
            :label="__('app.dashboard.view_all')"
            icon="o-arrow-right"
            link="{{ route('sell-invoices.index') }}"
            variant="ghost" size="sm"
        />
    </x-slot:menu>

    @if($recentInvoices->isEmpty())
        <div class="flex flex-col items-center justify-center py-10 text-base-content/40">
            <x-icon name="o-document-text" class="w-12 h-12 mb-3" />
            <p>{{ __('app.dashboard.no_invoices') }}</p>
            @if($isCurrentYear)
                <x-button
                    :label="__('app.invoices.create_title')"
                    icon="o-plus"
                    link="{{ route('sell-invoices.create') }}"
                    variant="primary" size="sm" class="mt-4"
                />
            @endif
        </div>
    @else
        @php
            $tableHeaders = [
                ['key' => 'number', 'label' => __('app.invoices.col_number'), 'class' => 'w-28', 'render' => fn($row) => '<span class="font-semibold whitespace-nowrap">' . e($row->number) . '</span>'],
                ['key' => 'date', 'label' => __('app.invoices.col_date'), 'class' => 'w-28', 'render' => fn($row) => '<span class="text-sm">' . $row->date->format('d/m/Y') . '</span>'],
                ['key' => 'contact.name', 'label' => __('app.invoices.col_customer'), 'sortable' => false, 'render' => fn($row) => '<span class="font-medium">' . e($row->contact?->name) . '</span>'],
                ['key' => 'total_gross', 'label' => __('app.invoices.col_total'), 'class' => 'w-36 text-right', 'render' => fn($row) => '<div class="text-right font-semibold">€ ' . number_format($row->total_gross / 100, 2, ',', '.') . '</div>'],
                ['key' => 'status', 'label' => __('app.invoices.col_status'), 'class' => 'w-28', 'view' => 'partials.invoice-status-cell'],
            ];
        @endphp
        <x-table
            :headers="$tableHeaders"
            :rows="$recentInvoices"
            link="/sell-invoices/{id}/edit"
        />
    @endif
</x-card>
