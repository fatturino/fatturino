<?php

use App\Contracts\SdiProvider;
use App\Models\Contact;
use App\Models\FiscalDocument;
use App\Services\PostHogTelemetryService;
use App\Services\ReportService;
use App\Settings\CompanySettings;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Dashboard')] class extends Component {
    public int $fiscalYear;

    public bool $isCurrentYear;

    public bool $hasVatAccounting;

    public bool $selfInvoicesEnabled;

    public array $stats = [];

    public function mount(CompanySettings $companySettings): void
    {
        $this->fiscalYear = (int) session('fiscal_year', now()->year);
        $this->isCurrentYear = $this->fiscalYear === now()->year;
        $this->hasVatAccounting = $companySettings->company_fiscal_regime !== 'RF19';
        $this->selfInvoicesEnabled = $this->hasVatAccounting || $companySettings->rf19_self_invoices_enabled;
        $this->loadStats();
        app(PostHogTelemetryService::class)->capture('dashboard_viewed', ['fiscal_year' => $this->fiscalYear], request()->user());
    }

    public function loadStats(): void
    {
        $stats = app(ReportService::class)->getDashboardStats($this->fiscalYear);
        $stats['hasInvoices'] = FiscalDocument::whereYear('date', $this->fiscalYear)->exists();
        $stats['hasSdi'] = app(SdiProvider::class)->isActivated();
        $stats['hasContacts'] = Contact::exists();
        $stats['recentInvoices'] = $stats['recentInvoices']->map(fn ($invoice) => [
            'id' => $invoice->id, 'number' => $invoice->number, 'contact' => $invoice->contact?->name,
            'due_date' => $this->formatDate($invoice->due_date) ?? $this->formatDate($invoice->date),
            'total_gross' => $invoice->total_gross, 'payment_status' => $invoice->paymentStatusValue(),
        ])->all();
        $stats['upcomingDueDates'] = $stats['upcomingDueDates']->map(function ($invoice) {
            $dueDate = $invoice->due_date === null ? null : Carbon::parse($invoice->due_date)->startOfDay();
            $daysUntilDue = $dueDate === null ? null : now()->startOfDay()->diffInDays($dueDate, false);

            return [
                'id' => $invoice->id,
                'contact' => $invoice->contact?->name,
                'due_date' => $this->formatDate($invoice->due_date),
                'total_gross' => $invoice->total_gross,
                'days_until_due' => $daysUntilDue,
            ];
        }
        )->all();
        $stats['topClients'] = $stats['topClients']->map(fn ($client) => ['contact' => $client->contact?->name, 'revenue_total' => $client->revenue_total])->all();
        $this->stats = $stats;
    }

    public function currency(int|float|null $cents): string
    {
        return '€ '.number_format(((int) $cents) / 100, 2, ',', '.');
    }

    public function paymentLabel(string $status): string
    {
        return match ($status) {
            'paid' => 'Pagata', 'overdue' => 'Scaduta', default => 'Da incassare'
        };
    }

    private function formatDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return ($value instanceof CarbonInterface ? $value : Carbon::parse($value))->format('d/m/Y');
    }
};
?>

<x-slot:header><div><p class="text-xs font-bold uppercase tracking-[.12em] text-content-muted">Panoramica</p><h1 class="text-lg font-bold text-content">Dashboard</h1></div></x-slot:header>

@php
    $collectionRate = $stats['revenueYtd'] > 0
        ? min(100, $stats['collectedNetYtd'] / $stats['revenueYtd'] * 100)
        : 0;
    $periodLabel = $isCurrentYear ? 'da inizio anno' : "nell'anno {$fiscalYear}";
    $outstandingDetail = $hasVatAccounting
        ? $stats['openInvoicesCount'].' documenti aperti · IVA '.$this->currency($stats['outstandingVatYtd'])
        : $stats['openInvoicesCount'].' documenti aperti';
    $collectionDetail = 'Tasso incasso '.number_format($collectionRate, 1, ',', '.').'%';
    $annualRevenueTrend = ($isCurrentYear ? 'Progressivo annuale' : 'Totale anno chiuso').' · IVA esclusa';
    $averageInvoiceDetail = $stats['invoicesThisMonth'].' fatture nel mese di riferimento';
    $topClientRevenue = max(array_column($stats['topClients'], 'revenue_total') ?: [1]);
    $overdueCount = $stats['paymentSummary']['overdue']['count'] ?? 0;
    $overdueNet = $stats['paymentSummary']['overdue']['outstanding_net'] ?? 0;
    $openNet = max(0, $stats['outstandingNetYtd'] - $overdueNet);
    $collectionHealthLabel = $overdueCount > 0
        ? 'Solleciti necessari'
        : ($openNet > 0 ? 'Incassi in attesa' : 'Tutto incassato');
    $collectionHealthClass = $overdueCount > 0
        ? 'bg-danger-bg text-danger'
        : ($openNet > 0 ? 'bg-warning-bg text-warning' : 'bg-success-bg text-success');
    $fiscalValue = $this->currency(abs($stats['vatBalanceYtd']));
    $fiscalDetail = $stats['vatBalanceYtd'] >= 0 ? 'Saldo IVA da versare' : 'Saldo IVA a credito';
@endphp

<section class="space-y-6">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div><p class="text-xs font-bold uppercase tracking-[.12em] text-content-muted">Dashboard operativa · {{ $fiscalYear }}</p><h2 class="mt-1 text-2xl font-bold text-content">Il polso della tua attività</h2><p class="mt-1 text-sm text-content-muted">Incassi, scadenze e andamento economico in un unico punto.</p></div>
        <div class="flex flex-wrap gap-2"><a href="{{ route('sell-invoices.create') }}" class="rounded-md bg-primary px-4 py-2 text-sm font-bold text-white">Nuova fattura</a>@if($selfInvoicesEnabled)<a href="{{ route('self-invoices.create') }}" class="rounded-md border border-border bg-white px-4 py-2 text-sm font-bold">Nuova autofattura</a>@endif<a href="{{ route('contacts.create') }}" class="rounded-md border border-border bg-white px-4 py-2 text-sm font-bold">Nuovo contatto</a></div>
    </div>
    @unless($isCurrentYear)<div class="rounded-lg border border-warning/30 bg-warning-bg p-4 text-sm text-warning">Visualizzazione in sola lettura per l'anno fiscale {{ $fiscalYear }}.</div>@endunless
    <div @class(['grid gap-4 sm:grid-cols-2', $hasVatAccounting ? 'xl:grid-cols-4' : 'xl:grid-cols-3'])>
        <x-dashboard.kpi-card label="Fatturato anno" :value="$this->currency($stats['revenueYtd'])" :trend="$annualRevenueTrend" trend-class="text-primary" :detail="$stats['invoicesYtd'].' fatture emesse'" />
        <x-dashboard.kpi-card label="Incassato netto" :value="$this->currency($stats['collectedNetYtd'])" :trend="number_format($collectionRate, 1, ',', '.').'% incassato'" trend-class="text-success" :detail="$collectionDetail" :progress="$collectionRate" />
        <x-dashboard.kpi-card label="Da incassare" :value="$this->currency($stats['outstandingNetYtd'])" :trend="$outstandingDetail" :trend-class="$overdueCount > 0 ? 'text-danger' : 'text-content-muted'" :detail="$outstandingDetail" />
        @if($hasVatAccounting)<x-dashboard.kpi-card label="Saldo IVA" :value="$fiscalValue" :trend="$fiscalDetail" :trend-class="$stats['vatBalanceYtd'] >= 0 ? 'text-warning' : 'text-success'" :detail="$fiscalDetail" />@endif
    </div>

    <div class="grid gap-6 xl:grid-cols-12">
        <div class="xl:col-span-8"><x-dashboard.revenue-chart :revenue-trend="$stats['revenueTrend']" :fiscal-year="$fiscalYear" /></div>
        <article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)] xl:col-span-4"><div class="flex items-center justify-between"><div><h2 class="font-bold">Da tenere d'occhio</h2><p class="mt-1 text-sm text-content-muted">Priorità sui documenti aperti</p></div><span class="rounded-full bg-surface-muted px-2.5 py-1 text-xs font-bold text-content-muted">{{ $stats['openInvoicesCount'] }} aperti</span></div><div class="mt-5 space-y-3"><a href="/sell-invoices?payment=overdue" class="dashboard-action-link"><span>Fatture scadute</span><span class="badge badge-overdue">{{ $overdueCount }}</span></a><a href="/sell-invoices?status=draft" class="dashboard-action-link"><span>Bozze da chiudere</span><span class="badge badge-draft">{{ $stats['draftCount'] }}</span></a><a href="/sell-invoices?status=xml_validated" class="dashboard-action-link"><span>Pronte per SDI</span><span class="badge badge-neutral">{{ $stats['readyForSdiCount'] }}</span></a></div><div class="mt-5 border-t border-border-light pt-4"><p class="text-xs font-bold uppercase tracking-[.08em] text-content-muted">Valore medio fattura</p><p class="mt-1 text-xl font-bold tabular-nums">{{ $this->currency($stats['averageInvoiceValue']) }}</p><p class="mt-1 text-sm text-content-muted">{{ $averageInvoiceDetail }}</p></div></article>
    </div>

    <div class="grid gap-6 xl:grid-cols-12"><article class="overflow-hidden rounded-xl border border-border-light bg-white shadow-[var(--shadow-card)] xl:col-span-8"><div class="flex items-center justify-between border-b border-border-light px-5 py-4"><div><h2 class="font-bold">Fatture recenti</h2><p class="mt-1 text-sm text-content-muted">Gli ultimi documenti emessi</p></div><a href="{{ route('sell-invoices.index') }}" class="text-sm font-semibold text-primary">Vedi tutte</a></div><div class="hidden grid-cols-12 gap-3 border-b border-border-light bg-surface-muted px-5 py-2 text-[11px] font-bold uppercase tracking-[.08em] text-content-muted sm:grid"><span class="col-span-4">Documento</span><span class="col-span-2">Stato</span><span class="col-span-2">Scadenza</span><span class="col-span-4 text-right">Totale</span></div><div class="divide-y divide-border-light">@forelse(array_slice($stats['recentInvoices'], 0, 6) as $invoice)<a href="{{ route('sell-invoices.edit', $invoice['id']) }}" class="block px-5 py-4 transition-colors hover:bg-surface-muted sm:grid sm:grid-cols-12 sm:items-center sm:gap-3"><div class="min-w-0 sm:col-span-4"><p class="font-semibold">{{ $invoice['number'] ?? '#'.$invoice['id'] }}</p><p class="truncate text-sm text-content-muted">{{ $invoice['contact'] ?? 'Cliente non associato' }}</p></div><div class="mt-2 sm:col-span-2 sm:mt-0"><span class="badge {{ $invoice['payment_status'] === 'overdue' ? 'badge-overdue' : ($invoice['payment_status'] === 'paid' ? 'badge-sent' : 'badge-draft') }}">{{ $this->paymentLabel($invoice['payment_status']) }}</span></div><p class="mt-2 text-sm text-content-muted sm:col-span-2 sm:mt-0">{{ $invoice['due_date'] }}</p><p class="mt-2 font-semibold tabular-nums sm:col-span-4 sm:mt-0 sm:text-right">{{ $this->currency($invoice['total_gross']) }}</p></a>@empty<p class="p-8 text-sm text-content-muted">Nessuna fattura disponibile.</p>@endforelse</div></article>
        <article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)] xl:col-span-4"><div class="flex items-center justify-between"><div><h2 class="font-bold">Clienti migliori</h2><p class="mt-1 text-sm text-content-muted">Per fatturato netto</p></div><a href="{{ route('contacts.index') }}" class="text-sm font-semibold text-primary">Vedi tutti</a></div><div class="mt-4 space-y-1">@forelse($stats['topClients'] as $index => $client)@php($clientShare = $topClientRevenue > 0 ? $client['revenue_total'] / $topClientRevenue * 100 : 0)<div class="relative overflow-hidden rounded-md px-3 py-3"><div class="absolute inset-y-0 left-0 bg-surface-muted" style="width: {{ $clientShare }}%"></div><div class="relative flex items-center justify-between gap-3"><p class="min-w-0 truncate text-sm font-medium"><span class="mr-2 text-content-muted">{{ $index + 1 }}</span>{{ $client['contact'] ?? 'Cliente' }}</p><span class="shrink-0 text-sm font-bold tabular-nums">{{ $this->currency($client['revenue_total']) }}</span></div></div>@empty<p class="py-8 text-center text-sm text-content-muted">Nessun cliente con fatturato disponibile.</p>@endforelse</div></article></div>

    <div class="grid gap-6 lg:grid-cols-2"><article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]"><div class="flex items-start justify-between gap-4"><div><h2 class="font-bold">Salute incassi</h2><p class="mt-1 text-sm text-content-muted">Rischio sul portafoglio aperto</p></div><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $collectionHealthClass }}">{{ $collectionHealthLabel }}</span></div><div class="mt-5 flex items-end justify-between gap-4"><div><p class="text-3xl font-bold tabular-nums">{{ number_format($collectionRate, 1, ',', '.') }}%</p><p class="mt-1 text-sm text-content-muted">tasso di incasso {{ $periodLabel }}</p></div><p class="text-right text-sm text-content-muted">{{ $this->currency($stats['collectedNetYtd']) }}<br>incassati</p></div><div class="mt-4 h-2 overflow-hidden rounded-full bg-surface-muted"><div class="h-full rounded-full bg-primary" style="width: {{ $collectionRate }}%"></div></div><div class="mt-5 grid gap-3 sm:grid-cols-2"><a href="/sell-invoices?payment=overdue" class="rounded-md border border-danger/20 bg-danger-bg px-3 py-3"><p class="text-xs font-bold uppercase tracking-[.08em] text-danger">Scaduto</p><p class="mt-1 font-bold tabular-nums text-danger">{{ $this->currency($overdueNet) }}</p><p class="mt-1 text-xs text-danger">{{ $overdueCount }} documenti da sollecitare</p></a><a href="/sell-invoices?payment=unpaid" class="rounded-md border border-border-light bg-surface-muted px-3 py-3"><p class="text-xs font-bold uppercase tracking-[.08em] text-content-muted">In attesa</p><p class="mt-1 font-bold tabular-nums">{{ $this->currency($openNet) }}</p><p class="mt-1 text-xs text-content-muted">{{ $openNet > 0 ? 'ancora nei termini' : 'nessun incasso aperto' }}</p></a></div>@if($hasVatAccounting)<p class="mt-4 text-xs text-content-muted">IVA incassata separata: {{ $this->currency($stats['collectedVatYtd']) }}</p>@endif</article><article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]"><div class="flex items-start justify-between"><div><h2 class="font-bold">Scadenze</h2><p class="mt-1 text-sm text-content-muted">Ordinate per urgenza</p></div><a href="/sell-invoices?payment=unpaid" class="text-sm font-semibold text-primary">Vedi aperte</a></div><div class="mt-5 space-y-1">@forelse($stats['upcomingDueDates'] as $invoice)@php($days = $invoice['days_until_due'])<a href="{{ route('sell-invoices.edit', $invoice['id']) }}" class="group flex items-center gap-3 rounded-md px-2 py-3 transition-colors hover:bg-surface-muted"><div class="flex size-10 shrink-0 flex-col items-center justify-center rounded-full {{ $days !== null && $days < 0 ? 'bg-danger-bg text-danger' : ($days !== null && $days <= 7 ? 'bg-warning-bg text-warning' : 'bg-surface-muted text-content-muted') }}"><span class="text-sm font-bold">{{ $days === null ? '—' : abs($days) }}</span><span class="text-[9px] font-bold uppercase">{{ $days === null ? '' : 'gg' }}</span></div><div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold">{{ $invoice['contact'] ?? 'Cliente' }}</p><p class="mt-0.5 text-xs text-content-muted">{{ $days === null ? $invoice['due_date'] : ($days < 0 ? 'Scaduta da '.abs($days).' giorni' : ($days === 0 ? 'In scadenza oggi' : 'Scade tra '.$days.' giorni')) }}</p></div><div class="text-right"><p class="text-sm font-bold tabular-nums">{{ $this->currency($invoice['total_gross']) }}</p><p class="mt-0.5 text-xs text-content-muted">{{ $invoice['due_date'] }}</p></div></a>@empty<p class="py-8 text-center text-sm text-content-muted">Nessuna scadenza imminente.</p>@endforelse</div></article></div>
</section>
