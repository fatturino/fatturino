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

new #[Layout('layouts::app')] #[Title('Oggi')] class extends Component {
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
            'id' => $invoice->id,
            'number' => $invoice->number,
            'contact' => $invoice->contact?->name,
            'date' => $this->formatDate($invoice->date),
            'total_net' => (int) $invoice->total_gross - (int) $invoice->total_vat,
            'total_vat' => (int) $invoice->total_vat,
            'payment_status' => $invoice->paymentStatusValue(),
        ])->all();
        $stats['upcomingDueDates'] = $stats['upcomingDueDates']->map(function ($invoice) {
            $dueDate = $invoice->due_date === null ? null : Carbon::parse($invoice->due_date)->startOfDay();

            return [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'contact' => $invoice->contact?->name,
                'due_date' => $this->formatDate($invoice->due_date),
                'remaining_balance' => $invoice->remainingBalance(),
                'days_until_due' => $dueDate === null ? null : now()->startOfDay()->diffInDays($dueDate, false),
            ];
        }
        )->all();
        $this->stats = $stats;
    }

    public function currency(int|float|null $cents): string
    {
        return '€ '.number_format(((int) $cents) / 100, 2, ',', '.');
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

<x-slot:header>
    <div>
        <p class="text-xs font-medium text-content-muted">Panoramica</p>
        <h1 class="text-lg font-semibold text-content">Oggi</h1>
    </div>
</x-slot:header>

@php
    $periodLabel = $isCurrentYear ? 'da inizio anno' : "nell'anno {$fiscalYear}";
    $overdueCount = $stats['paymentSummary']['overdue']['count'] ?? 0;
    $overdueNet = $stats['paymentSummary']['overdue']['outstanding_net'] ?? 0;
    $partialCount = $stats['paymentSummary']['partial']['count'] ?? 0;
    $summaryItems = [
        ['label' => 'Fatturato', 'value' => $this->currency($stats['revenueYtd']), 'detail' => $stats['invoicesYtd'].' '.($stats['invoicesYtd'] === 1 ? 'fattura emessa' : 'fatture emesse'), 'period' => $periodLabel.' · IVA esclusa', 'href' => '/sell-invoices'],
        ['label' => 'Da incassare', 'value' => $this->currency($stats['outstandingNetYtd']), 'detail' => $stats['openInvoicesCount'].' '.($stats['openInvoicesCount'] === 1 ? 'documento aperto' : 'documenti aperti'), 'period' => $periodLabel.' · netto residuo', 'href' => '/sell-invoices?payment=open'],
        ['label' => 'Scaduto', 'value' => $this->currency($overdueNet), 'detail' => $overdueCount.' '.($overdueCount === 1 ? 'documento da sollecitare' : 'documenti da sollecitare'), 'period' => $periodLabel.' · netto residuo', 'href' => '/sell-invoices?payment=overdue', 'tone' => $overdueCount > 0 ? 'danger' : 'default'],
    ];
    $attentionItems = [];
    $attentionAction = $isCurrentYear ? 'Apri' : 'Consulta';
    if ($overdueCount > 0) {
        $attentionItems[] = ['title' => 'Fatture scadute', 'detail' => $overdueCount.' '.($overdueCount === 1 ? 'fattura richiede un sollecito' : 'fatture richiedono un sollecito'), 'value' => $this->currency($overdueNet), 'href' => '/sell-invoices?payment=overdue', 'tone' => 'danger', 'action' => $attentionAction.' scadute'];
    }
    if ($stats['readyForSdiCount'] > 0) {
        $attentionItems[] = $stats['hasSdi']
            ? ['title' => 'Fatture pronte per SDI', 'detail' => $stats['readyForSdiCount'].' '.($stats['readyForSdiCount'] === 1 ? 'fattura validata è pronta per l’invio' : 'fatture validate sono pronte per l’invio'), 'value' => null, 'href' => '/sell-invoices?status=xml_validated', 'tone' => 'info', 'action' => $attentionAction.' da inviare']
            : ['title' => 'Invio SDI da configurare', 'detail' => $stats['readyForSdiCount'].' '.($stats['readyForSdiCount'] === 1 ? 'fattura validata attende la configurazione del servizio' : 'fatture validate attendono la configurazione del servizio'), 'value' => null, 'href' => route('settings.openapi'), 'tone' => 'warning', 'action' => $isCurrentYear ? 'Configura SDI' : 'Consulta'];
    }
    if ($partialCount > 0) {
        $attentionItems[] = ['title' => 'Incassi parziali', 'detail' => $partialCount.' '.($partialCount === 1 ? 'fattura ha un residuo da incassare' : 'fatture hanno un residuo da incassare'), 'value' => null, 'href' => '/sell-invoices?payment=partial', 'tone' => 'warning', 'action' => $attentionAction.' parziali'];
    }
    $firstDueDate = collect($stats['upcomingDueDates'])->first(fn ($invoice) => ($invoice['days_until_due'] ?? -1) >= 0);
@endphp

<section class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-medium text-content-muted">Anno fiscale {{ $fiscalYear }}</p>
            <p class="mt-1 text-sm text-content-muted">{{ $isCurrentYear ? 'Priorità, incassi e documenti aggiornati per oggi.' : "Riepilogo dell'anno fiscale {$fiscalYear}." }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs text-content-muted" aria-live="polite">Aggiornato ora</span>
            <button wire:click="loadStats" wire:loading.attr="disabled" type="button" class="inline-flex h-10 items-center justify-center rounded-lg border border-border bg-white px-3 text-sm font-medium text-content transition hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-primary/20"><span wire:loading.remove wire:target="loadStats">Aggiorna</span><span wire:loading wire:target="loadStats">Aggiornamento…</span></button>
            @if($isCurrentYear)
                <x-app-link :href="route('sell-invoices.create')" class="inline-flex h-10 items-center justify-center rounded-lg bg-primary px-4 text-sm font-medium text-white transition hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary/20">Nuova fattura</x-app-link>
            @endif
        </div>
    </div>

    @unless($isCurrentYear)
        <p class="rounded-lg border border-warning/30 bg-warning-bg px-4 py-3 text-sm text-warning">Visualizzazione in sola lettura per l'anno fiscale {{ $fiscalYear }}.</p>
    @endunless

    @if(! $stats['hasInvoices'] && ! $stats['hasContacts'])
        <div class="rounded-xl border border-info/20 bg-info-bg p-5"><h2 class="font-semibold text-content">Inizia dalla tua prima fattura</h2><p class="mt-1 text-sm text-content-muted">Crea un contatto e poi registra una fattura: qui vedrai incassi, scadenze e attività recente.</p>@if($isCurrentYear)<div class="mt-4 flex flex-wrap gap-2"><x-app-link :href="route('contacts.create')" class="inline-flex h-10 items-center rounded-lg border border-border bg-white px-3 text-sm font-medium text-content">Nuovo contatto</x-app-link><x-app-link :href="route('sell-invoices.create')" class="inline-flex h-10 items-center rounded-lg bg-primary px-3 text-sm font-medium text-white">Nuova fattura</x-app-link></div>@endif</div>
    @endif

    <x-dashboard.summary :items="$summaryItems" />

    <div class="grid gap-6 xl:grid-cols-12">
        <div class="xl:col-span-7"><x-dashboard.attention-queue :items="$attentionItems" :first-due-date="$firstDueDate" /></div>
        <div class="xl:col-span-5">
            <article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]">
                <div class="flex items-start justify-between gap-4"><div><h2 class="font-semibold text-content">Prossime scadenze</h2><p class="mt-1 text-sm text-content-muted">Per data di pagamento prevista</p></div><x-app-link href="/sell-invoices?payment=open" class="shrink-0 text-sm font-medium text-primary hover:underline">Vedi aperte</x-app-link></div>
                <div class="mt-4 divide-y divide-border-light">
                    @forelse($stats['upcomingDueDates'] as $invoice)
                        @php($days = $invoice['days_until_due'])
                        <x-app-link :href="route('sell-invoices.edit', $invoice['id'])" class="dashboard-list-link -mx-2 flex items-center gap-3 border-0 px-2"><span @class(['flex size-10 shrink-0 flex-col items-center justify-center rounded-full text-center', 'bg-danger-bg text-danger' => $days !== null && $days < 0, 'bg-warning-bg text-warning' => $days !== null && $days >= 0 && $days <= 7, 'bg-surface-muted text-content-muted' => $days === null || $days > 7])><span class="text-sm font-bold">{{ $days === null ? '—' : abs($days) }}</span><span class="text-[9px] font-bold uppercase">{{ $days === null ? '' : 'gg' }}</span></span><span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium text-content">{{ $invoice['contact'] ?? 'Cliente non associato' }}</span><span class="mt-0.5 block text-xs text-content-muted">{{ $days === null ? $invoice['due_date'] : ($days < 0 ? 'Scaduta da '.abs($days).' giorni' : ($days === 0 ? 'In scadenza oggi' : 'Scade tra '.$days.' giorni')) }}</span></span><span class="shrink-0 text-right text-sm font-semibold tabular-nums text-content">{{ $this->currency($invoice['remaining_balance']) }}</span></x-app-link>
                    @empty
                        <p class="py-8 text-center text-sm text-content-muted">Nessuna scadenza aperta nel periodo.</p>
                    @endforelse
                </div>
            </article>
        </div>
    </div>

    <x-dashboard.recent-document-list :invoices="$stats['recentInvoices']" />

    @if($hasVatAccounting)
        <div class="flex flex-wrap items-center justify-between gap-3 border-y border-border py-4 text-sm"><div><span class="font-medium text-content">Saldo IVA {{ $periodLabel }}</span><span class="ml-2 tabular-nums text-content-muted">{{ $this->currency(abs($stats['vatBalanceYtd'])) }} {{ $stats['vatBalanceYtd'] >= 0 ? 'da versare' : 'a credito' }}</span></div><span class="text-xs text-content-muted">IVA incassata separata: {{ $this->currency($stats['collectedVatYtd']) }}</span></div>
    @endif

    <x-dashboard.revenue-chart :revenue-trend="$stats['revenueTrend']" :revenue-projection="$stats['revenueProjection']" :revenue-ytd="$stats['revenueYtd']" :fiscal-year="$fiscalYear" />
</section>
