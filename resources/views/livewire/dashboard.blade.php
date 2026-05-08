<div>
    {{-- HEADER --}}
    <x-header :title="__('app.dashboard.title')" separator progress-indicator>
        <x-slot:actions>
            @if($isCurrentYear)
                <x-button
                    icon="o-plus"
                    variant="primary"
                    :label="__('app.invoices.create_title')"
                    link="{{ route('sell-invoices.create') }}"
                />
            @endif
        </x-slot:actions>
    </x-header>

    {{-- Read-only banner for concluded fiscal years --}}
    @unless($isCurrentYear)
        <x-alert
            :title="__('app.dashboard.readonly_year_title', ['year' => $fiscalYear])"
            icon="o-lock-closed"
            variant="warning"
        />
    @endunless

    {{-- ROW 1: KPI stats --}}
    <x-dashboard.kpi-stats
        :is-current-year="$isCurrentYear"
        :fiscal-year="$fiscalYear"
        :revenue-this-month="$revenueThisMonth"
        :month-change-percent="$monthChangePercent"
        :revenue-ytd="$revenueYtd"
        :invoices-ytd="$invoicesYtd"
        :invoices-this-month="$invoicesThisMonth"
        :average-invoice-value="$averageInvoiceValue"
        :active-clients-count="$activeClientsCount"
        :total-contacts-count="$totalContactsCount"
    />

    {{-- ROW 2: Top clients + Quick actions --}}
    <div class="grid lg:grid-cols-3 gap-4 mt-4">
        <div class="lg:col-span-2">
            <x-dashboard.top-clients :top-clients="$topClients" />
        </div>
        <x-dashboard.quick-actions :is-current-year="$isCurrentYear" />
    </div>

    {{-- ROW 3: Recent invoices --}}
    <div class="mt-4">
        <x-dashboard.recent-invoices
            :recent-invoices="$recentInvoices"
            :is-current-year="$isCurrentYear"
        />
    </div>

    {{-- ROW 4: Bottom widgets --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
        <x-dashboard.fiscal-summary
            :vat-collected-ytd="$vatCollectedYtd"
            :vat-on-purchases-ytd="$vatOnPurchasesYtd"
            :vat-balance-ytd="$vatBalanceYtd"
            :vat-by-quarter="$vatByQuarter"
            :is-current-year="$isCurrentYear"
            :fiscal-year="$fiscalYear"
        />
        <x-dashboard.payment-overview
            :payment-summary="$paymentSummary"
            :upcoming-due-dates="$upcomingDueDates"
        />
        <x-dashboard.revenue-chart :revenue-trend="$revenueTrend" />
    </div>
</div>
