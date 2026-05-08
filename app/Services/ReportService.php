<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\PurchaseInvoice;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Calculates all business reporting metrics used across the application.
 * Each public method is independently queryable and testable in isolation.
 *
 * All methods accept a $year parameter. For the current year, date ranges end
 * at Carbon::now() (year-to-date). For past years, ranges span the full calendar
 * year (Jan 1 – Dec 31).
 */
class ReportService
{
    /**
     * Total gross revenue from invoices issued in the reference month (in cents).
     * For the current year, uses the current calendar month.
     * For past years, uses December of that year.
     */
    public function revenueThisMonth(int $year = 0): int
    {
        $year = $year ?: now()->year;
        $referenceMonth = $this->referenceMonth($year);

        return (int) Invoice::whereBetween('date', [
            $referenceMonth->copy()->startOfMonth(),
            $referenceMonth->copy()->endOfMonth(),
        ])->sum('total_gross');
    }

    /**
     * Total gross revenue from invoices issued in the month preceding the reference month (in cents).
     * For the current year, uses the previous calendar month.
     * For past years, uses November of that year.
     */
    public function revenueLastMonth(int $year = 0): int
    {
        $year = $year ?: now()->year;
        $referenceMonth = $this->referenceMonth($year)->subMonth();

        return (int) Invoice::whereBetween('date', [
            $referenceMonth->copy()->startOfMonth(),
            $referenceMonth->copy()->endOfMonth(),
        ])->sum('total_gross');
    }

    /**
     * Total gross revenue for the given year.
     * For the current year: Jan 1 to now (YTD).
     * For past years: Jan 1 to Dec 31 (full year).
     */
    public function revenueYtd(int $year = 0): int
    {
        $year = $year ?: now()->year;
        [$start, $end] = $this->yearDateRange($year);

        return (int) Invoice::whereBetween('date', [$start, $end])->sum('total_gross');
    }

    /**
     * Number of invoices issued in the reference month.
     * For the current year, uses the current calendar month.
     * For past years, uses December of that year.
     */
    public function invoicesThisMonth(int $year = 0): int
    {
        $year = $year ?: now()->year;
        $referenceMonth = $this->referenceMonth($year);

        return Invoice::whereBetween('date', [
            $referenceMonth->copy()->startOfMonth(),
            $referenceMonth->copy()->endOfMonth(),
        ])->count();
    }

    /**
     * Number of invoices issued in the given year (YTD or full year).
     */
    public function invoicesYtd(int $year = 0): int
    {
        $year = $year ?: now()->year;
        [$start, $end] = $this->yearDateRange($year);

        return Invoice::whereBetween('date', [$start, $end])->count();
    }

    /**
     * Number of unique contacts who received at least one invoice in the given year.
     */
    public function activeClientsCount(int $year = 0): int
    {
        $year = $year ?: now()->year;
        [$start, $end] = $this->yearDateRange($year);

        return Invoice::whereBetween('date', [$start, $end])
            ->distinct('contact_id')
            ->count('contact_id');
    }

    /**
     * Total number of registered contacts.
     */
    public function totalContactsCount(): int
    {
        return Contact::count();
    }

    /**
     * Average gross invoice value for the given year (in cents). Returns 0 if no invoices exist.
     */
    public function averageInvoiceValueYtd(int $year = 0): int
    {
        $year = $year ?: now()->year;
        $count = $this->invoicesYtd($year);

        if ($count === 0) {
            return 0;
        }

        return intdiv($this->revenueYtd($year), $count);
    }

    /**
     * Month-over-month revenue change as a percentage for the given year.
     */
    public function monthChangePercent(int $year = 0): float
    {
        $year = $year ?: now()->year;

        return $this->computeMonthChangePercent(
            $this->revenueThisMonth($year),
            $this->revenueLastMonth($year)
        );
    }

    /**
     * Total withholding tax (ritenuta d'acconto) for the given year (in cents).
     */
    public function withholdingTaxYtd(int $year = 0): int
    {
        $year = $year ?: now()->year;
        [$start, $end] = $this->yearDateRange($year);

        return (int) Invoice::whereBetween('date', [$start, $end])
            ->where('withholding_tax_enabled', true)
            ->sum('withholding_tax_amount');
    }

    /**
     * Total VAT charged on all invoices for the given year (in cents).
     */
    public function vatCollectedYtd(int $year = 0): int
    {
        $year = $year ?: now()->year;
        [$start, $end] = $this->yearDateRange($year);

        return (int) Invoice::whereBetween('date', [$start, $end])->sum('total_vat');
    }

    /**
     * Total VAT on purchase invoices (supplier invoices) for the given year (in cents).
     */
    public function vatOnPurchasesYtd(int $year = 0): int
    {
        $year = $year ?: now()->year;
        [$start, $end] = $this->yearDateRange($year);

        return (int) PurchaseInvoice::betweenDates($start, $end)->sum('total_vat');
    }

    /**
     * VAT balance broken down by quarter for the given year.
     * Returns array of ['q' => 1..4, 'collected' => int, 'purchases' => int, 'balance' => int]
     */
    public function vatByQuarter(int $year = 0): array
    {
        $year = $year ?: now()->year;
        $quarters = [];

        for ($q = 1; $q <= 4; $q++) {
            $start = sprintf('%04d-%02d-01', $year, ($q - 1) * 3 + 1);
            $end = sprintf('%04d-%02d-%02d', $year, $q * 3, (new \DateTime("$year-".($q*3)."-01"))->format('t'));

            $collected = (int) Invoice::whereBetween('date', [$start, $end])->sum('total_vat');
            $purchases = (int) PurchaseInvoice::betweenDates($start, $end)->sum('total_vat');

            $quarters[] = [
                'q' => $q,
                'collected' => $collected,
                'purchases' => $purchases,
                'balance' => $collected - $purchases,
            ];
        }

        return $quarters;
    }

    /**
     * Monthly revenue for current and previous year (12 months each, in cents).
     * Returns ['current' => [int x12], 'previous' => [int x12], 'labels' => [string x12]]
     */
    public function monthlyRevenueTrend(int $year = 0): array
    {
        $year = $year ?: now()->year;
        $current = [];
        $previous = [];
        $labels = ['G', 'F', 'M', 'A', 'M', 'G', 'L', 'A', 'S', 'O', 'N', 'D'];

        for ($m = 1; $m <= 12; $m++) {
            $start = sprintf('%04d-%02d-01', $year, $m);
            $end = sprintf('%04d-%02d-%02d', $year, $m, (new \DateTime("$year-$m-01"))->format('t'));
            $current[] = (int) Invoice::whereBetween('date', [$start, $end])->sum('total_gross');

            $prevStart = sprintf('%04d-%02d-01', $year - 1, $m);
            $prevEnd = sprintf('%04d-%02d-%02d', $year - 1, $m, (new \DateTime(($year-1)."-$m-01"))->format('t'));
            $previous[] = (int) Invoice::whereBetween('date', [$prevStart, $prevEnd])->sum('total_gross');
        }

        return [
            'labels' => $labels,
            'current' => $current,
            'previous' => $previous,
        ];
    }

    /**
     * Top N contacts ranked by gross revenue for the given year, descending.
     */
    public function topClients(int $limit = 5, int $year = 0): Collection
    {
        $year = $year ?: now()->year;
        [$start, $end] = $this->yearDateRange($year);

        return Invoice::whereBetween('date', [$start, $end])
            ->selectRaw('contact_id, SUM(total_gross) as revenue_total')
            ->groupBy('contact_id')
            ->orderByDesc('revenue_total')
            ->with('contact')
            ->limit($limit)
            ->get();
    }

    /**
     * Most recent N invoices for the given year, ordered by date descending.
     */
    public function recentInvoices(int $limit = 8, int $year = 0): Collection
    {
        $year = $year ?: now()->year;
        [$start, $end] = $this->yearDateRange($year);

        return Invoice::whereBetween('date', [$start, $end])
            ->with('contact')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Payment status breakdown for the given year: count and total per status.
     */
    public function paymentSummary(int $year = 0): array
    {
        $year = $year ?: now()->year;
        [$start, $end] = $this->yearDateRange($year);

        $statuses = Invoice::whereBetween('date', [$start, $end])
            ->selectRaw('payment_status, COUNT(*) as count, COALESCE(SUM(total_gross), 0) as total')
            ->groupBy('payment_status')
            ->pluck('total', 'payment_status')
            ->toArray();

        $counts = Invoice::whereBetween('date', [$start, $end])
            ->selectRaw('payment_status, COUNT(*) as count')
            ->groupBy('payment_status')
            ->pluck('count', 'payment_status')
            ->toArray();

        // Build a structured array keyed by PaymentStatus enum values
        $summary = [];
        foreach (PaymentStatus::cases() as $status) {
            $summary[$status->value] = [
                'count' => $counts[$status->value] ?? 0,
                'total' => (int) ($statuses[$status->value] ?? 0),
            ];
        }

        return $summary;
    }

    /**
     * Invoices with upcoming due dates (unpaid/partial only), ordered by due_date asc.
     */
    public function upcomingDueDates(int $limit = 5, int $year = 0): Collection
    {
        $year = $year ?: now()->year;
        [$start, $end] = $this->yearDateRange($year);

        return Invoice::whereBetween('date', [$start, $end])
            ->whereNotNull('due_date')
            ->whereIn('payment_status', [PaymentStatus::Unpaid, PaymentStatus::Partial, PaymentStatus::Overdue])
            ->with('contact')
            ->orderBy('due_date')
            ->limit($limit)
            ->get();
    }

    /**
     * Forecasted cashflow for the next 6 months plus an "overdue" bucket.
     *
     * Each bucket contains:
     *   - key:      YYYY-MM string (or 'overdue')
     *   - label:    human-readable label in Italian
     *   - inflows:  balance due on unpaid/partial sales invoices (total_gross - total_paid) in cents
     *   - outflows: balance due on unpaid/partial purchase invoices in cents
     *   - net:      inflows - outflows in cents
     *
     * Only invoices with a non-null due_date are included.
     */
    public function cashflowForecast(): array
    {
        $today = Carbon::today();

        // Build bucket definitions: overdue + 6 monthly forward buckets
        $bucketDefs = [
            [
                'key' => 'overdue',
                'label' => __('app.dashboard.overdue'),
                'start' => null,
                'end' => $today->copy()->subDay()->endOfDay(),
            ],
        ];

        for ($i = 0; $i < 6; $i++) {
            $monthRef = $today->copy()->addMonths($i);
            $bucketDefs[] = [
                'key' => $monthRef->format('Y-m'),
                'label' => ucfirst($monthRef->locale('it')->isoFormat('MMMM YYYY')),
                'start' => $i === 0 ? $today->copy() : $monthRef->copy()->startOfMonth(),
                'end' => $monthRef->copy()->endOfMonth(),
            ];
        }

        $unpaidStatuses = [
            PaymentStatus::Unpaid->value,
            PaymentStatus::Partial->value,
            PaymentStatus::Overdue->value,
        ];

        $buckets = [];

        foreach ($bucketDefs as $bucketDef) {
            $salesQuery = Invoice::whereIn('payment_status', $unpaidStatuses)
                ->whereNotNull('due_date');

            $purchaseQuery = PurchaseInvoice::whereIn('payment_status', $unpaidStatuses)
                ->whereNotNull('due_date');

            if ($bucketDef['start'] === null) {
                $salesQuery->where('due_date', '<=', $bucketDef['end']);
                $purchaseQuery->where('due_date', '<=', $bucketDef['end']);
            } else {
                $salesQuery->whereBetween('due_date', [$bucketDef['start'], $bucketDef['end']]);
                $purchaseQuery->whereBetween('due_date', [$bucketDef['start'], $bucketDef['end']]);
            }

            $inflows = (int) $salesQuery
                ->selectRaw('COALESCE(SUM(total_gross - total_paid), 0) as balance_due')
                ->value('balance_due');

            $outflows = (int) $purchaseQuery
                ->selectRaw('COALESCE(SUM(total_gross - total_paid), 0) as balance_due')
                ->value('balance_due');

            $buckets[] = [
                'key' => $bucketDef['key'],
                'label' => $bucketDef['label'],
                'inflows' => $inflows,
                'outflows' => $outflows,
                'net' => $inflows - $outflows,
            ];
        }

        return $buckets;
    }

    /**
     * All dashboard statistics as a named array ready to be passed to a view.
     * Pre-computes shared values to avoid redundant DB queries for derived metrics.
     */
    public function getDashboardStats(int $year = 0): array
    {
        $year = $year ?: now()->year;

        $revenueThisMonth = $this->revenueThisMonth($year);
        $revenueLastMonth = $this->revenueLastMonth($year);
        $revenueYtd = $this->revenueYtd($year);
        $invoicesYtd = $this->invoicesYtd($year);
        $vatCollectedYtd = $this->vatCollectedYtd($year);
        $vatOnPurchasesYtd = $this->vatOnPurchasesYtd($year);

        return [
            'revenueThisMonth' => $revenueThisMonth,
            'revenueLastMonth' => $revenueLastMonth,
            'revenueYtd' => $revenueYtd,
            'invoicesThisMonth' => $this->invoicesThisMonth($year),
            'invoicesYtd' => $invoicesYtd,
            'activeClientsCount' => $this->activeClientsCount($year),
            'totalContactsCount' => $this->totalContactsCount(),
            // Derived from already-fetched values — avoids two extra DB queries
            'averageInvoiceValue' => $invoicesYtd > 0 ? intdiv($revenueYtd, $invoicesYtd) : 0,
            'monthChangePercent' => $this->computeMonthChangePercent($revenueThisMonth, $revenueLastMonth),
            'withholdingTaxYtd' => $this->withholdingTaxYtd($year),
            'vatCollectedYtd' => $vatCollectedYtd,
            'vatOnPurchasesYtd' => $vatOnPurchasesYtd,
            'vatBalanceYtd' => $vatCollectedYtd - $vatOnPurchasesYtd,
            'vatByQuarter' => $this->vatByQuarter($year),
            'topClients' => $this->topClients(5, $year),
            'recentInvoices' => $this->recentInvoices(8, $year),
            'paymentSummary' => $this->paymentSummary($year),
            'upcomingDueDates' => $this->upcomingDueDates(5, $year),
            'cashflowForecast' => $this->cashflowForecast($year),
            'revenueTrend' => $this->monthlyRevenueTrend($year),
            'draftCount' => Invoice::where('status', 'draft')->whereYear('date', $year)->count(),
            'readyForSdiCount' => Invoice::whereIn('status', ['generated', 'xml_validated'])->whereYear('date', $year)->count(),
        ];
    }

    /**
     * Returns the [start, end] date range for a given year.
     * Current year: Jan 1 to now (YTD). Past year: Jan 1 to Dec 31 (full year).
     */
    private function yearDateRange(int $year): array
    {
        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = ($year === now()->year)
            ? Carbon::now()
            : Carbon::create($year, 12, 31)->endOfDay();

        return [$start, $end];
    }

    /**
     * Returns the reference month for "this month" metrics.
     * Current year: the actual current month. Past year: December of that year.
     */
    private function referenceMonth(int $year): Carbon
    {
        return ($year === now()->year)
            ? Carbon::now()
            : Carbon::create($year, 12, 1);
    }

    /**
     * Compute percentage change between two revenue values.
     */
    private function computeMonthChangePercent(int $revenueThisMonth, int $revenueLastMonth): float
    {
        if ($revenueLastMonth > 0) {
            return (($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100;
        }

        return $revenueThisMonth > 0 ? 100.0 : 0.0;
    }
}
