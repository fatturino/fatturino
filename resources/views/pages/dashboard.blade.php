<?php

use App\Contracts\SdiProvider;
use App\Enums\InvoiceStatus;
use App\Models\Contact;
use App\Models\FiscalDocument;
use App\Models\ProformaInvoice;
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
            $daysUntilDue = $dueDate === null ? null : now()->startOfDay()->diffInDays($dueDate, false);
            [$dueTone, $dueLabel, $dueDetail, $isUrgent] = match (true) {
                $daysUntilDue === null => ['default', 'Data da verificare', 'Nessuna data prevista', false],
                $daysUntilDue < 0 => ['danger', 'Scaduta', 'Scaduta da '.abs($daysUntilDue).' '.(abs($daysUntilDue) === 1 ? 'giorno' : 'giorni'), true],
                $daysUntilDue === 0 => ['danger', 'Scade oggi', 'Pagamento previsto oggi', true],
                $daysUntilDue <= 7 => ['warning', 'Urgente', 'Scade tra '.$daysUntilDue.' '.($daysUntilDue === 1 ? 'giorno' : 'giorni'), true],
                $daysUntilDue <= 30 => ['info', 'Imminente', 'Scade tra '.$daysUntilDue.' giorni', false],
                default => ['default', 'Futura', 'Scade tra '.$daysUntilDue.' giorni', false],
            };

            return [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'contact' => $invoice->contact?->name,
                'email' => $invoice->contact?->email,
                'due_date' => $this->formatDate($invoice->due_date),
                'remaining_balance' => $invoice->remainingBalance(),
                'days_until_due' => $daysUntilDue,
                'due_tone' => $dueTone,
                'due_label' => $dueLabel,
                'due_detail' => $dueDetail,
                'is_urgent' => $isUrgent,
                'reminder_scenario' => $daysUntilDue < 0 ? 'overdue' : 'upcoming',
                'reminder_label' => $daysUntilDue < 0 ? 'Sollecito scaduta' : 'Promemoria scadenza',
                'payment_reminder_count' => (int) ($invoice->payment_reminder_count ?? 0),
                'last_payment_reminder_at' => $this->formatDate($invoice->last_payment_reminder_at),
            ];
        }
        )->all();
        $stats['proformaIssuanceDeadlines'] = app(ReportService::class)
            ->paidProformasAwaitingIssuedInvoice()
            ->map(fn (ProformaInvoice $proforma) => $this->proformaIssuanceDeadline($proforma, $this->isCurrentYear))
            ->sortBy([
                ['sort_order', 'asc'],
                ['due_date_sort', 'asc'],
                ['id', 'asc'],
            ])
            ->values()
            ->all();
        $stats['f24Upcoming'] = collect($stats['f24Upcoming'] ?? [])
            ->map(fn ($payment) => [
                'id' => $payment->id,
                'label' => $payment->primaryLabel(),
                'due_date' => $this->formatDate($payment->due_date),
                'amount_cents' => (int) $payment->balance_cents,
                'is_overdue' => $payment->isOverdue(),
                'href' => route('f24.edit', $payment),
            ])
            ->all();
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

    private function proformaIssuanceDeadline(ProformaInvoice $proforma, bool $canCreateInvoice): array
    {
        $totalPaid = 0;
        $settlementPayment = $proforma->payments
            ->sortBy(fn ($payment) => [
                $payment->paid_at?->format('Y-m-d') ?? '9999-12-31',
                $payment->id,
            ])
            ->first(function ($payment) use (&$totalPaid, $proforma) {
                $totalPaid += (int) $payment->amount;

                return $totalPaid >= $proforma->net_due;
            }
            );
        $settlementDate = $settlementPayment?->paid_at?->startOfDay();
        $dueDate = $settlementDate?->copy()->addDays(12);
        $daysUntilDue = $dueDate === null ? null : now()->startOfDay()->diffInDays($dueDate, false);
        $linkedInvoice = $proforma->convertedInvoice;
        $hasLinkedDraft = $linkedInvoice !== null && in_array($linkedInvoice->status, [
            InvoiceStatus::Draft,
            InvoiceStatus::Generated,
            InvoiceStatus::XmlValidated,
        ], true);
        [$tone, $deadlineLabel, $deadlineDetail, $sortOrder] = match (true) {
            $daysUntilDue === null => ['warning', 'Data saldo da verificare', 'Registra la data del pagamento che ha saldato la proforma.', 2],
            $daysUntilDue < 0 => ['danger', 'Scaduta', 'In ritardo di '.abs($daysUntilDue).' '.(abs($daysUntilDue) === 1 ? 'giorno' : 'giorni'), 0],
            $daysUntilDue === 0 => ['danger', 'Scade oggi', 'Ultimo giorno per emettere la fattura elettronica.', 0],
            default => ['warning', 'Scade tra '.$daysUntilDue.' '.($daysUntilDue === 1 ? 'giorno' : 'giorni'), 'Termine per emettere la fattura elettronica.', 1],
        };

        return [
            'id' => $proforma->id,
            'type' => 'proforma_issuance',
            'title' => $proforma->contact?->name ?: 'Cliente da verificare',
            'detail' => 'Proforma '.($proforma->number ?: 'senza riferimento').' · Saldo: '.($this->formatDate($settlementDate) ?? 'da verificare'),
            'value' => $deadlineLabel,
            'meta' => $dueDate === null ? $deadlineDetail : 'Emissione entro '.$this->formatDate($dueDate).' · '.$deadlineDetail,
            'href' => $hasLinkedDraft ? route('sell-invoices.edit', $linkedInvoice) : route('proforma.edit', $proforma),
            'tone' => $tone,
            'action' => $hasLinkedDraft ? 'Apri fattura' : ($canCreateInvoice ? 'Crea fattura' : 'Apri proforma'),
            'convert_action' => ! $hasLinkedDraft && $canCreateInvoice ? route('proforma.convert', $proforma) : null,
            'sort_order' => $sortOrder,
            'due_date_sort' => $dueDate?->toDateString() ?? '9999-12-31',
        ];
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
    $reminderCount = count($stats['upcomingDueDates']);
    if ($reminderCount > 0) {
        $attentionItems[] = ['title' => 'Solleciti da preparare', 'detail' => $reminderCount.' '.($reminderCount === 1 ? 'fattura richiede attenzione' : 'fatture richiedono attenzione'), 'value' => null, 'href' => '#payment-reminders', 'tone' => $overdueCount > 0 ? 'danger' : 'warning', 'action' => 'Apri scadenze'];
    }
    if ($stats['readyForSdiCount'] > 0) {
        $attentionItems[] = $stats['hasSdi']
            ? ['title' => 'Fatture pronte per SDI', 'detail' => $stats['readyForSdiCount'].' '.($stats['readyForSdiCount'] === 1 ? 'fattura validata è pronta per l’invio' : 'fatture validate sono pronte per l’invio'), 'value' => null, 'href' => '/sell-invoices?status=xml_validated', 'tone' => 'info', 'action' => $attentionAction.' da inviare']
            : ['title' => 'Invio SDI da configurare', 'detail' => $stats['readyForSdiCount'].' '.($stats['readyForSdiCount'] === 1 ? 'fattura validata attende la configurazione del servizio' : 'fatture validate attendono la configurazione del servizio'), 'value' => null, 'href' => route('settings.openapi'), 'tone' => 'warning', 'action' => $isCurrentYear ? 'Configura SDI' : 'Consulta'];
    }
    if ($partialCount > 0) {
        $attentionItems[] = ['title' => 'Incassi parziali', 'detail' => $partialCount.' '.($partialCount === 1 ? 'fattura ha un residuo da incassare' : 'fatture hanno un residuo da incassare'), 'value' => null, 'href' => '/sell-invoices?payment=partial', 'tone' => 'warning', 'action' => $attentionAction.' parziali'];
    }
    $f24AttentionItems = collect($stats['f24Upcoming'] ?? [])
        ->map(function (array $payment) {
            $dueLabel = $payment['due_date'] ?? __('app.f24.status_planned');

            return [
                'title' => $payment['label'],
                'detail' => $payment['is_overdue']
                    ? __('app.f24.attention_overdue', ['date' => $dueLabel])
                    : __('app.f24.attention_upcoming', ['date' => $dueLabel]),
                'value' => $this->currency($payment['amount_cents']),
                'href' => $payment['href'],
                'tone' => $payment['is_overdue'] ? 'danger' : 'warning',
                'action' => __('app.f24.attention_action'),
            ];
        })
        ->all();
    $attentionItems = [
        ...$stats['proformaIssuanceDeadlines'],
        ...$f24AttentionItems,
        ...$attentionItems,
    ];
    $firstDueDate = null;
    $attentionUrgencyCount = count($attentionItems);
@endphp

<section class="dashboard-page space-y-7 lg:space-y-8">
    <div class="dashboard-heading flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-content sm:text-3xl">Panoramica finanziaria</h2>
            <p class="mt-2 text-sm text-content-muted">Anno fiscale {{ $fiscalYear }} · {{ $isCurrentYear ? 'Priorità, incassi e documenti aggiornati per oggi.' : "Riepilogo dell'anno fiscale {$fiscalYear}." }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs text-content-muted" role="status" aria-live="polite"><span wire:loading.remove wire:target="loadStats">Aggiornato ora</span><span wire:loading wire:target="loadStats">Aggiornamento dati in corso</span></span>
            <button wire:click="loadStats" wire:loading.attr="disabled" wire:loading.attr="aria-busy" wire:target="loadStats" type="button" class="btn-outline inline-flex h-11 items-center justify-center px-4 text-sm"><span wire:loading.remove wire:target="loadStats">Aggiorna</span><span wire:loading wire:target="loadStats">Aggiornamento…</span></button>
            @if($isCurrentYear)
                <x-app-link :href="route('sell-invoices.create')" class="btn-brand inline-flex h-11 items-center justify-center px-4 text-sm">Nuova fattura</x-app-link>
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
        <div class="xl:col-span-7"><x-dashboard.attention-queue :items="$attentionItems" :first-due-date="$firstDueDate" :urgency-count="$attentionUrgencyCount" /></div>
        <div class="xl:col-span-5 space-y-6">
            <x-dashboard.upcoming-due-dates :invoices="$stats['upcomingDueDates']" />
            <x-dashboard.f24-summary
                :paid-ytd="$stats['f24PaidYtd'] ?? 0"
                :planned-total="$stats['f24PlannedTotal'] ?? 0"
                :overdue-total="$stats['f24OverdueTotal'] ?? 0"
                :upcoming="$stats['f24Upcoming'] ?? []"
            />
        </div>
    </div>

    <x-dashboard.recent-document-list :invoices="$stats['recentInvoices']" />

    @if($hasVatAccounting)
        @php
            $f24IvaPaid = (int) ($stats['f24IvaPaidYtd'] ?? 0);
            $f24IvaPlanned = (int) ($stats['f24IvaPlannedYtd'] ?? 0);
            $f24IvaTotal = $f24IvaPaid + $f24IvaPlanned;
            $vatCheck = (int) $stats['vatBalanceYtd'] - $f24IvaPaid;
        @endphp
        <section class="dashboard-vat-summary grid gap-4 p-4 sm:grid-cols-3 sm:items-end" aria-labelledby="vat-summary-title">
            <div>
                <h2 id="vat-summary-title" class="text-sm font-semibold text-content">Saldo IVA {{ $periodLabel }}</h2>
                <p class="mt-1 text-sm text-content-muted">
                    <span class="font-semibold tabular-nums text-content">{{ $this->currency(abs($stats['vatBalanceYtd'])) }}</span>
                    {{ $stats['vatBalanceYtd'] >= 0 ? 'da versare' : 'a credito' }}
                </p>
                <p class="mt-1 text-xs text-content-muted">{{ __('app.f24.vat_check_from_docs') }}</p>
            </div>
            <div>
                <p class="text-sm font-semibold text-content">{{ __('app.f24.iva_summary') }}</p>
                <p class="mt-1 text-sm text-content-muted">
                    <span class="font-semibold tabular-nums text-content">{{ $this->currency($f24IvaTotal) }}</span>
                </p>
                <p class="mt-1 text-xs text-content-muted">
                    {{ __('app.f24.iva_paid') }}:
                    <span class="font-semibold tabular-nums text-content-secondary">{{ $this->currency($f24IvaPaid) }}</span>
                    · {{ __('app.f24.iva_planned') }}:
                    <span class="font-semibold tabular-nums text-content-secondary">{{ $this->currency($f24IvaPlanned) }}</span>
                </p>
            </div>
            <div class="sm:text-right">
                <p class="text-sm font-semibold text-content">{{ __('app.f24.vat_check_title') }}</p>
                <p class="mt-1 text-sm text-content-muted">
                    <span class="font-semibold tabular-nums text-content">{{ $this->currency(abs($vatCheck)) }}</span>
                    {{ $vatCheck >= 0 ? __('app.f24.vat_check_still_due') : __('app.f24.vat_check_overpaid') }}
                </p>
                <p class="mt-1 text-xs leading-5 text-content-muted">
                    {{ __('app.f24.vat_check_hint') }}
                    · IVA incassata separata
                    <span class="font-semibold tabular-nums text-content-secondary">{{ $this->currency($stats['collectedVatYtd']) }}</span>
                </p>
            </div>
        </section>
    @endif

    <x-dashboard.revenue-chart :revenue-trend="$stats['revenueTrend']" :revenue-projection="$stats['revenueProjection']" :revenue-ytd="$stats['revenueYtd']" :fiscal-year="$fiscalYear" />
</section>
