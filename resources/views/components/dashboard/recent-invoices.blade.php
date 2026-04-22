@props(['recentInvoices', 'isCurrentYear'])

<x-card :title="__('app.dashboard.recent_invoices')">
    <x-slot:menu>
        <x-button
            :label="__('app.dashboard.view_all')"
            icon="o-arrow-right"
            link="{{ route('sell-invoices.index') }}"
            class="btn-ghost btn-sm"
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
                    class="btn-primary btn-sm mt-4"
                />
            @endif
        </div>
    @else
        <x-table
            :headers="[
                ['key' => 'number', 'label' => __('app.invoices.col_number'), 'class' => 'w-28'],
                ['key' => 'date',   'label' => __('app.invoices.col_date'),   'class' => 'w-28'],
                ['key' => 'contact.name', 'label' => __('app.invoices.col_customer'), 'sortable' => false],
                ['key' => 'total_gross',  'label' => __('app.invoices.col_total'),    'class' => 'w-36 text-right'],
                ['key' => 'status',       'label' => __('app.invoices.col_status'),   'class' => 'w-28'],
            ]"
            :rows="$recentInvoices"
            link="/sell-invoices/{id}/edit"
        >
            @scope('cell_date', $invoice)
                {{ $invoice->date->format('d/m/Y') }}
            @endscope

            @scope('cell_total_gross', $invoice)
                <span class="font-semibold">€ {{ number_format($invoice->total_gross / 100, 2, ',', '.') }}</span>
            @endscope

            @scope('cell_status', $invoice)
                @if($invoice->sdi_status)
                    {{-- SDI status takes priority when invoice has been submitted --}}
                    <x-badge :value="$invoice->sdi_status->label()" :class="$invoice->sdi_status->color()" />
                @else
                    <x-badge :value="$invoice->status->label()" :class="$invoice->status->color()" />
                @endif
            @endscope
        </x-table>
    @endif
</x-card>
