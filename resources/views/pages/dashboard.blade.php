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
        $stats['upcomingDueDates'] = $stats['upcomingDueDates']->map(fn ($invoice) => [
            'id' => $invoice->id, 'contact' => $invoice->contact?->name, 'due_date' => $this->formatDate($invoice->due_date), 'total_gross' => $invoice->total_gross,
        ])->all();
        $stats['topClients'] = $stats['topClients']->map(fn ($client) => ['contact' => $client->contact?->name, 'revenue_total' => $client->revenue_total])->all();
        $this->stats = $stats;
    }

    public function currency(int|float|null $cents): string { return '€ '.number_format(((int) $cents) / 100, 2, ',', '.'); }
    public function paymentLabel(string $status): string { return match ($status) { 'paid' => 'Pagata', 'overdue' => 'Scaduta', default => 'Da incassare' }; }
    private function formatDate(mixed $value): ?string { if ($value === null || $value === '') return null; return ($value instanceof CarbonInterface ? $value : Carbon::parse($value))->format('d/m/Y'); }
}; ?>

<x-slot:header><div><p class="text-xs font-bold uppercase tracking-[.12em] text-content-muted">Panoramica</p><h1 class="text-lg font-bold text-content">Dashboard</h1></div></x-slot:header>

<section class="space-y-6">
    <div class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)] sm:p-7">
        <div class="grid gap-6 lg:grid-cols-[1fr_.8fr]"><div><p class="text-xs font-bold uppercase tracking-[.12em] text-content-muted">Dashboard operativa · Anno {{ $fiscalYear }}</p><h2 class="mt-2 text-2xl font-bold text-content">Incassi, scadenze e documenti da chiudere</h2><p class="mt-2 max-w-2xl text-sm leading-6 text-content-muted">{{ $hasVatAccounting ? 'Priorità del giorno e andamento economico in un solo punto, con IVA separata dai flussi operativi.' : 'Priorità del giorno e andamento economico in un solo punto, senza voci IVA non rilevanti per il regime forfettario.' }}</p><div class="mt-5 flex flex-wrap gap-2"><a href="{{ route('sell-invoices.create') }}" class="rounded-md bg-primary px-4 py-2 text-sm font-bold text-white">Nuova fattura</a>@if($selfInvoicesEnabled)<a href="{{ route('self-invoices.create') }}" class="rounded-md border border-border px-4 py-2 text-sm font-bold">Nuova autofattura</a>@endif<a href="{{ route('contacts.create') }}" class="rounded-md border border-border px-4 py-2 text-sm font-bold">Nuovo contatto</a></div></div>
            <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-1"><x-dashboard.metric label="Da incassare netto" :value="$this->currency($stats['outstandingNetYtd'])" detail="{$stats['openInvoicesCount']} documenti aperti" /><x-dashboard.metric label="Incassato netto YTD" :value="$this->currency($stats['collectedNetYtd'])" detail="Tasso incasso ".number_format($stats['revenueYtd'] > 0 ? min(100, $stats['collectedNetYtd'] / $stats['revenueYtd'] * 100) : 0, 1, ',', '.').'%'" /><x-dashboard.metric label="Fatturato netto mese" :value="$this->currency($stats['revenueThisMonth'])" detail="{$stats['monthChangePercent']}% vs mese scorso" /></div></div>
        <div class="mt-5 grid gap-2 border-t border-border-light pt-4 sm:grid-cols-3"><a href="/sell-invoices?payment=overdue" class="dashboard-action-link"><span>Fatture scadute</span><span class="badge badge-overdue">{{ $stats['paymentSummary']['overdue']['count'] ?? 0 }}</span></a><a href="/sell-invoices?status=draft" class="dashboard-action-link"><span>Bozze da chiudere</span><span class="badge badge-draft">{{ $stats['draftCount'] }}</span></a><a href="/sell-invoices?status=xml_validated" class="dashboard-action-link"><span>Pronte per SDI</span><span class="badge badge-neutral">{{ $stats['readyForSdiCount'] }}</span></a></div>
    </div>
    @unless($isCurrentYear)<div class="rounded-lg border border-warning/30 bg-warning-bg p-4 text-sm text-warning">Visualizzazione in sola lettura per l'anno fiscale {{ $fiscalYear }}.</div>@endunless
    <div class="grid gap-6 xl:grid-cols-12"><article class="overflow-hidden rounded-xl border border-border-light bg-white shadow-[var(--shadow-card)] xl:col-span-8"><div class="flex items-center justify-between border-b border-border-light px-5 py-4"><h2 class="font-bold">Fatture recenti</h2><a href="{{ route('sell-invoices.index') }}" class="text-sm font-semibold text-primary">Vedi tutte</a></div><div class="divide-y divide-border-light">@forelse($stats['recentInvoices'] as $invoice)<a href="{{ route('sell-invoices.edit', $invoice['id']) }}" class="flex items-center justify-between gap-3 px-5 py-4 hover:bg-surface-muted"><div><p class="font-semibold">{{ $invoice['number'] ?? '#'.$invoice['id'] }}</p><p class="text-sm text-content-muted">{{ $invoice['contact'] ?? 'Cliente non associato' }} · {{ $invoice['due_date'] }}</p></div><div class="text-right"><span class="badge {{ $invoice['payment_status'] === 'overdue' ? 'badge-overdue' : ($invoice['payment_status'] === 'paid' ? 'badge-sent' : 'badge-draft') }}">{{ $this->paymentLabel($invoice['payment_status']) }}</span><p class="mt-1 font-semibold tabular-nums">{{ $this->currency($invoice['total_gross']) }}</p></div></a>@empty<p class="p-8 text-sm text-content-muted">Nessuna fattura disponibile.</p>@endforelse</div></article>
        <article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)] xl:col-span-4"><h2 class="font-bold">Scadenze</h2><p class="mt-1 text-sm text-content-muted">Prossime scadenze</p><div class="mt-4 space-y-2">@forelse($stats['upcomingDueDates'] as $invoice)<a href="{{ route('sell-invoices.edit', $invoice['id']) }}" class="dashboard-list-link"><p class="font-medium">{{ $invoice['contact'] ?? 'Cliente' }}</p><p class="text-xs text-content-muted">{{ $invoice['due_date'] }} · {{ $this->currency($invoice['total_gross']) }}</p></a>@empty<p class="text-sm text-content-muted">Nessuna scadenza imminente.</p>@endforelse</div></article></div>
    <div class="grid gap-6 lg:grid-cols-2"><article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]"><h2 class="font-bold">Salute incassi</h2><p class="mt-3 text-2xl font-bold">{{ number_format($stats['revenueYtd'] > 0 ? min(100, $stats['collectedNetYtd'] / $stats['revenueYtd'] * 100) : 0, 1, ',', '.') }}%</p><div class="mt-3 h-2 rounded-full bg-surface-muted"><div class="h-2 rounded-full bg-primary" style="width: {{ $stats['revenueYtd'] > 0 ? min(100, $stats['collectedNetYtd'] / $stats['revenueYtd'] * 100) : 0 }}%"></div></div><p class="mt-2 text-sm text-content-muted">{{ $this->currency($stats['collectedNetYtd']) }} incassati su {{ $this->currency($stats['revenueYtd']) }}</p></article><article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]"><div class="flex justify-between"><h2 class="font-bold">Clienti migliori</h2><a href="{{ route('contacts.index') }}" class="text-sm font-semibold text-primary">Vedi tutti</a></div><div class="mt-3 space-y-2">@forelse($stats['topClients'] as $index => $client)<div class="flex justify-between rounded-md border border-border-light px-3 py-2"><span>{{ $index + 1 }}. {{ $client['contact'] ?? 'Cliente' }}</span><span class="font-semibold">{{ $this->currency($client['revenue_total']) }}</span></div>@empty<p class="text-sm text-content-muted">Nessun cliente con fatturato disponibile.</p>@endforelse</div></article></div>
</section>
