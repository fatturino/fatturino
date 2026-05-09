<div>
    {{-- Read-only banner for concluded fiscal years --}}
    @unless($isCurrentYear)
        <x-alert
            :title="__('app.dashboard.readonly_year_title', ['year' => $fiscalYear])"
            icon="o-lock-closed"
            variant="warning"
        />
    @endunless

    {{-- First-run setup checklist --}}
    @if($isCurrentYear && !$hasInvoices)
        <div class="mb-8 bg-primary rounded-2xl p-6 sm:p-8 text-white">
            <h2 class="text-xl font-bold mb-1">{{ __('app.dashboard.welcome_title') }}</h2>
            <p class="text-white/60 text-sm mb-6">{{ __('app.dashboard.welcome_desc') }}</p>

            <div class="space-y-2">
                {{-- Step 1: Account (always done after wizard) --}}
                <div class="flex items-center gap-3 text-sm bg-white/10 rounded-lg px-4 py-3">
                    <x-icon name="o-check-circle" class="w-5 h-5 text-success shrink-0" />
                    <span class="text-white/80">{{ __('app.dashboard.setup_step_account') }}</span>
                </div>

                {{-- Step 2: SDI Configuration --}}
                @if($hasSdi)
                    <div class="flex items-center gap-3 text-sm bg-white/10 rounded-lg px-4 py-3">
                        <x-icon name="o-check-circle" class="w-5 h-5 text-success shrink-0" />
                        <span class="text-white/80">{{ __('app.dashboard.setup_step_sdi_done') }}</span>
                    </div>
                @else
                    <a href="/electronic-invoice-settings" wire:navigate class="flex items-center gap-3 text-sm bg-white/15 hover:bg-white/20 rounded-lg px-4 py-3 transition-colors group">
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-white/20 text-xs font-bold shrink-0">2</span>
                        <span class="flex-1 text-white">{{ __('app.dashboard.setup_step_sdi') }}</span>
                        <x-icon name="o-arrow-right" class="w-4 h-4 text-white/50 group-hover:text-white transition-colors shrink-0" />
                    </a>
                @endif

                {{-- Step 3: Contacts --}}
                @if($hasContacts)
                    <div class="flex items-center gap-3 text-sm bg-white/10 rounded-lg px-4 py-3">
                        <x-icon name="o-check-circle" class="w-5 h-5 text-success shrink-0" />
                        <span class="text-white/80">{{ __('app.dashboard.setup_step_contacts_done') }}</span>
                    </div>
                @else
                    <a href="/contacts/create" wire:navigate class="flex items-center gap-3 text-sm bg-white/15 hover:bg-white/20 rounded-lg px-4 py-3 transition-colors group">
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-white/20 text-xs font-bold shrink-0">3</span>
                        <span class="flex-1 text-white">{{ __('app.dashboard.setup_step_contacts') }}</span>
                        <x-icon name="o-arrow-right" class="w-4 h-4 text-white/50 group-hover:text-white transition-colors shrink-0" />
                    </a>
                @endif

                {{-- Step 4: First invoice --}}
                <a href="{{ route('sell-invoices.create') }}" wire:navigate class="flex items-center gap-3 text-sm bg-amber-400/20 hover:bg-amber-400/30 rounded-lg px-4 py-3 transition-colors group border border-amber-400/30">
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-amber-400 text-primary text-xs font-bold shrink-0">4</span>
                    <span class="flex-1 text-amber-200 font-medium">{{ __('app.dashboard.setup_step_invoice') }}</span>
                    <x-icon name="o-arrow-right" class="w-4 h-4 text-amber-300 group-hover:text-amber-200 transition-colors shrink-0" />
                </a>
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
    <div class="grid lg:grid-cols-3 gap-4 mt-6">
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
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6 mb-4">
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
