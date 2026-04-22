@props([
    'isCurrentYear',
    'fiscalYear',
    'revenueThisMonth',
    'monthChangePercent',
    'revenueYtd',
    'invoicesYtd',
    'invoicesThisMonth',
    'averageInvoiceValue',
    'activeClientsCount',
    'totalContactsCount',
])

@php
    $monthLabel   = $isCurrentYear
        ? __('app.dashboard.stat_revenue_month')
        : __('app.dashboard.stat_revenue_dec', ['year' => $fiscalYear]);
    $vsMonthLabel = $isCurrentYear
        ? __('app.dashboard.vs_last_month')
        : __('app.dashboard.vs_nov', ['year' => $fiscalYear]);
    $ytdLabel     = $isCurrentYear
        ? __('app.dashboard.stat_revenue_ytd')
        : __('app.dashboard.stat_revenue_year', ['year' => $fiscalYear]);
@endphp

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

    {{-- Revenue this month (or December for past years) with trend vs previous month --}}
    <x-stat
        :title="$monthLabel"
        :value="'€ ' . number_format($revenueThisMonth / 100, 2, ',', '.')"
        icon="o-banknotes"
        :description="$monthChangePercent >= 0
            ? '+' . number_format($monthChangePercent, 1) . '% ' . $vsMonthLabel
            : number_format($monthChangePercent, 1) . '% ' . $vsMonthLabel"
        :color="$monthChangePercent >= 0 ? 'text-success' : 'text-error'"
    />

    {{-- Revenue year-to-date (or full year for past years) --}}
    <x-stat
        :title="$ytdLabel"
        :value="'€ ' . number_format($revenueYtd / 100, 2, ',', '.')"
        icon="o-arrow-trending-up"
        :description="$invoicesYtd . ' ' . __('app.dashboard.invoices_issued')"
    />

    {{-- Invoices this month with average value --}}
    <x-stat
        :title="__('app.dashboard.stat_invoices_month')"
        :value="$invoicesThisMonth"
        icon="o-document-text"
        :description="__('app.dashboard.avg_invoice') . ' € ' . number_format($averageInvoiceValue / 100, 2, ',', '.')"
    />

    {{-- Active clients this year --}}
    <x-stat
        :title="__('app.dashboard.stat_active_clients')"
        :value="$activeClientsCount"
        icon="o-users"
        :description="$totalContactsCount . ' ' . __('app.dashboard.total_contacts')"
    />

</div>
