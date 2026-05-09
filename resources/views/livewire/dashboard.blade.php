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

    {{-- First-run welcome banner --}}
    @if($isCurrentYear && !$hasInvoices)
        <div class="mb-8 bg-gradient-to-br from-primary to-secondary rounded-2xl p-8 sm:p-10 text-white relative overflow-hidden">
            {{-- Subtle pattern --}}
            <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 20px 20px;"></div>
            <div class="relative z-10 max-w-2xl">
                <h2 class="text-2xl font-bold mb-3">{{ __('app.dashboard.welcome_title') }}</h2>
                <p class="text-white/80 text-base mb-6">{{ __('app.dashboard.welcome_desc') }}</p>
                <div class="flex flex-wrap gap-3">
                    <x-button :label="__('app.dashboard.welcome_cta')" icon="o-plus" variant="primary" link="{{ route('sell-invoices.create') }}" class="!bg-white !text-primary hover:!bg-white/90" size="lg" />
                    <x-button :label="__('app.dashboard.welcome_contacts')" icon="o-users" link="/contacts" variant="ghost" class="!text-white hover:!bg-white/10" />
                </div>
            </div>
        </div>
    @endif

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

    {{-- ROW 3: Operational overview --}}
    <div class="mt-4">
        <x-dashboard.operational-overview
            :recent-invoices="$recentInvoices"
            :is-current-year="$isCurrentYear"
            :revenue-trend="$revenueTrend"
            :draft-count="$draftCount"
            :ready-for-sdi-count="$readyForSdiCount"
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
