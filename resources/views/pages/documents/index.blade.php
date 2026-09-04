<?php

use App\Actions\DeleteUnconvertedProforma;
use App\Models\CreditNote;
use App\Models\ProformaInvoice;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\SelfInvoice;
use App\Settings\CompanySettings;
use App\Support\FiscalRegimePolicy;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app')] class extends Component {
    use WithPagination;

    #[Locked]
    public string $type;

    #[Url(as: 'search', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: '')]
    public string $status = '';

    #[Url(as: 'payment', except: '')]
    public string $payment = '';

    #[Url(as: 'sort', except: 'date')]
    public string $sort = 'date';

    #[Url(as: 'direction', except: 'desc')]
    public string $direction = 'desc';

    #[Locked]
    public int $fiscalYear;

    public function mount(string $type): void
    {
        abort_unless(in_array($type, array_keys($this->definitions()), true), 404);
        if ($type === 'self') {
            $settings = app(CompanySettings::class);
            abort_unless(FiscalRegimePolicy::supportsSelfInvoices($settings->company_fiscal_regime, $settings->rf19_self_invoices_enabled), 403);
        }
        $this->type = $type;
        $this->fiscalYear = (int) session('fiscal_year', now()->year);
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'status', 'payment', 'sort', 'direction'], true)) {
            $this->resetPage();
        }
    }

    public function selectTab(string $value): void
    {
        if (in_array($value, ['unpaid', 'overdue'], true) && $this->hasPayments()) {
            $this->payment = $value;
            $this->status = '';
        } else {
            $this->status = $value;
            $this->payment = '';
        }
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, ['number', 'date', 'total_gross'], true)) {
            return;
        }
        $this->direction = $this->sort === $column && $this->direction === 'asc' ? 'desc' : 'asc';
        $this->sort = $column;
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'status', 'payment');
        $this->resetPage();
    }

    public function deleteProforma(int $proformaId, DeleteUnconvertedProforma $deleteUnconvertedProforma): bool
    {
        abort_unless($this->type === 'proforma', 404);
        $deleted = $deleteUnconvertedProforma->execute(
            ProformaInvoice::query()->findOrFail($proformaId)
        );
        if (! $deleted) {
            $this->addError('proforma', 'La proforma è stata convertita e non può essere eliminata.');

            return false;
        }

        return true;
    }

    public function render()
    {
        $query = $this->query();
        $documents = (clone $query)
            ->orderBy(in_array($this->sort, ['number', 'date', 'total_gross'], true) ? $this->sort : 'date', $this->direction === 'asc' ? 'asc' : 'desc')
            ->paginate(15);
        $aggregates = $this->aggregates();
        $total = (int) $aggregates->total;
        $net = (int) $aggregates->net;
        $vat = (int) $aggregates->vat;
        $drafts = (int) $aggregates->drafts;
        $sent = (int) $aggregates->sent;
        $open = (int) ($aggregates->open ?? 0);
        $overdue = (int) ($aggregates->overdue ?? 0);

        return view('pages::documents.index', compact('documents', 'total', 'net', 'vat', 'drafts', 'sent', 'open', 'overdue'));
    }

    public function title(): string
    {
        return $this->definition()['title'];
    }

    public function definition(): array
    {
        return $this->definitions()[$this->type];
    }

    public function hasPayments(): bool
    {
        return $this->definition()['payments'];
    }

    public function isActive(string $value): bool
    {
        return $value === '' ? $this->status === '' && $this->payment === '' : ($value === 'unpaid' || $value === 'overdue' ? $this->payment === $value : $this->status === $value);
    }

    public function tabLabel(string $value): string
    {
        return match ($value) {
            '' => 'Tutte', 'draft' => 'Bozze', 'unpaid' => 'Da pagare', 'overdue' => 'Scadute', 'sent' => 'Inviate', 'converted' => 'Convertite', 'xml_validated' => 'Salvate', default => $value
        };
    }

    public function money(int|float|null $value): string
    {
        return '€ '.number_format(((int) $value) / 100, 2, ',', '.');
    }

    public function statusLabel(mixed $value): string
    {
        return match ($this->statusValue($value)) {
            'draft' => 'Bozza',
            'generated' => 'Generata',
            'xml_validated' => 'Validata',
            'sent' => 'Inviata',
            'converted' => 'Convertita',
            'cancelled' => 'Annullata',
            'paid' => 'Pagata',
            'partial' => 'Parziale',
            'unpaid' => 'Da pagare',
            'overdue' => 'Scaduta',
            'delivered' => 'Consegnata',
            'not_delivered' => 'Mancata consegna',
            'accepted' => 'Accettata',
            'received' => 'Ricevuta',
            'rejected' => 'Scartata',
            'refused' => 'Rifiutata',
            'error' => 'Errore',
            default => $this->statusValue($value),
        };
    }

    public function statusTone(mixed $value): string
    {
        return match ($this->statusValue($value)) {
            'paid', 'sent', 'converted', 'delivered', 'accepted' => 'success',
            'overdue', 'rejected', 'refused', 'error', 'cancelled' => 'danger',
            'unpaid', 'partial', 'not_delivered' => 'warning',
            'draft', 'generated' => 'neutral',
            default => 'info',
        };
    }

    private function baseQuery(): Builder
    {
        $this->ensureAllowedType();
        $class = $this->definition()['model'];

        return $class::query()
            ->where('date', '>=', $this->fiscalYear.'-01-01')
            ->where('date', '<', ($this->fiscalYear + 1).'-01-01');
    }

    private function ensureAllowedType(): void
    {
        abort_unless(in_array($this->type, array_keys($this->definitions()), true), 404);
        if ($this->type === 'self') {
            $settings = app(CompanySettings::class);
            abort_unless(FiscalRegimePolicy::supportsSelfInvoices($settings->company_fiscal_regime, $settings->rf19_self_invoices_enabled), 403);
        }
    }

    private function query(): Builder
    {
        $query = $this->baseQuery()->with(['contact:id,name,email']);
        if ($this->hasPayments()) {
            $query->with('payments:id,fiscal_document_id,amount,paid_at,reference,notes,bank_name');
        }
        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->where(fn ($q) => $q
                ->where('number', 'like', $term)
                ->orWhereHas('contact', fn ($c) => $c->where('name', 'like', $term)));
        }
        if ($this->status !== '') {
            $query->where('status', $this->status);
        }
        if ($this->payment !== '' && $this->hasPayments()) {
            $this->payment === 'open'
                ? $query->whereIn('payment_status', ['unpaid', 'partial', 'overdue'])
                    : $query->where('payment_status', $this->payment);
        }

        return $query;
    }

    private function aggregates(): object
    {
        $select = [
            'count(*) as total',
            'coalesce(sum(total_gross - total_vat), 0) as net',
            'coalesce(sum(total_vat), 0) as vat',
            "sum(case when status = 'draft' then 1 else 0 end) as drafts",
            "sum(case when status = 'sent' then 1 else 0 end) as sent",
        ];
        if ($this->hasPayments()) {
            $select[] = "sum(case when payment_status in ('unpaid', 'partial', 'overdue') then 1 else 0 end) as open";
            $select[] = "sum(case when payment_status = 'overdue' then 1 else 0 end) as overdue";
        }

        return $this->baseQuery()->selectRaw(implode(', ', $select))->firstOrFail();
    }

    private function statusValue(mixed $value): string
    {
        return $value instanceof BackedEnum ? (string) $value->value : (string) $value;
    }

    private function definitions(): array
    {
        return [
            'sales' => ['model' => SalesInvoice::class, 'title' => 'Fatture di Vendita', 'singular' => 'fattura', 'plural' => 'fatture', 'contact' => 'Cliente', 'base' => 'sell-invoices', 'create' => 'Nuova fattura', 'payments' => true, 'tabs' => ['', 'draft', 'unpaid', 'overdue']],
            'purchase' => ['model' => PurchaseInvoice::class, 'title' => 'Fatture di Acquisto', 'singular' => 'fattura di acquisto', 'plural' => 'fatture', 'contact' => 'Fornitore', 'base' => 'purchase-invoices', 'create' => null, 'payments' => true, 'tabs' => ['', 'draft', 'unpaid', 'overdue']],
            'self' => ['model' => SelfInvoice::class, 'title' => 'Autofatture', 'singular' => 'autofattura', 'plural' => 'autofatture', 'contact' => 'Fornitore', 'base' => 'self-invoices', 'create' => 'Nuova autofattura', 'payments' => true, 'tabs' => ['', 'draft', 'unpaid', 'overdue']],
            'proforma' => ['model' => ProformaInvoice::class, 'title' => 'Proforma', 'singular' => 'proforma', 'plural' => 'proforma', 'contact' => 'Cliente', 'base' => 'proforma', 'create' => 'Nuova proforma', 'payments' => false, 'tabs' => ['', 'draft', 'sent', 'converted']],
            'credit' => ['model' => CreditNote::class, 'title' => 'Note di Credito', 'singular' => 'nota di credito', 'plural' => 'note di credito', 'contact' => 'Cliente', 'base' => 'credit-notes', 'create' => 'Nuova nota di credito', 'payments' => false, 'tabs' => ['', 'draft', 'xml_validated', 'sent']],
        ];
    }
};
?>

<x-slot:header>
    <div>
        <p class="text-xs font-medium text-content-muted">Documenti</p>
        <h1 class="text-lg font-semibold text-content">{{ $this->title() }}</h1>
    </div>
</x-slot:header>

@php
    $definition = $this->definition();
    $actionDocuments = $documents->getCollection()->map(fn ($document) => [
        'id' => $document->id,
        'number' => $document->number,
        'contactId' => $document->contact_id,
        'status' => $this->statusValue($document->status),
        'sdiEditable' => $document->isSdiEditable(),
        'paymentStatus' => $this->statusValue($document->payment_status),
        'totalGross' => (int) $document->total_gross,
        'netDue' => (int) $document->net_due,
        'totalPaid' => (int) $document->total_paid,
        'payments' => $this->hasPayments() ? $document->payments->map(fn ($payment) => [
            'id' => $payment->id,
            'amount' => (int) $payment->amount,
            'paidAt' => $payment->paid_at?->format('Y-m-d'),
            'reference' => $payment->reference,
            'notes' => $payment->notes,
            'bankName' => $payment->bank_name,
        ])->values() : [],
    ])->values();
    $linkableInvoices = $type === 'proforma'
        ? SalesInvoice::query()
            ->whereNull('proforma_id')
            ->whereIn('contact_id', $documents->getCollection()->pluck('contact_id')->filter()->unique())
            ->orderByDesc('date')
            ->get(['id', 'number', 'contact_id', 'date', 'total_gross'])
            ->map(fn ($invoice) => ['id' => $invoice->id, 'number' => $invoice->number, 'contactId' => $invoice->contact_id, 'date' => $invoice->date->format('Y-m-d'), 'totalGross' => (int) $invoice->total_gross])
            ->values()
        : [];
    $actionConfig = [
        'type' => $type,
        'base' => '/'.$definition['base'],
        'documents' => $actionDocuments,
        'canEmail' => in_array($type, ['sales', 'credit', 'proforma'], true),
        'canPay' => $this->hasPayments(),
        'canXml' => in_array($type, ['sales', 'self', 'credit'], true),
        'linkableInvoices' => $linkableInvoices,
    ];
    $summaryParts = [
        $total.' '.$definition['plural'],
        $this->money($net).' netto',
    ];
    if ($this->hasPayments()) {
        $summaryParts[] = $open.' da saldare';
    } else {
        $summaryParts[] = $drafts.' bozze';
        $summaryParts[] = $sent.' inviate';
    }
@endphp
<section
    class="space-y-6"
    x-data="documentActionCenter(@js($actionConfig))"
    x-on:document-action.window="handleAction($event.detail)"
>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-medium text-content-muted">Anno fiscale {{ $fiscalYear }}</p>
            <p class="mt-1 text-sm text-content">{{ implode(' · ', $summaryParts) }}@if($this->hasPayments() && $overdue > 0) <span class="text-danger">· {{ $overdue }} {{ $overdue === 1 ? 'scaduta' : 'scadute' }}</span>@endif</p>
        </div>

        @if($definition['create'])
            <x-app-link href="/{{ $definition['base'] }}/create" class="inline-flex h-11 items-center justify-center rounded-lg bg-primary px-4 text-sm font-medium text-white transition hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary/20">
                {{ $definition['create'] }}
            </x-app-link>
        @endif
    </div>

    @if($type === 'purchase')
        <p class="rounded-lg border border-info/20 bg-info-bg px-4 py-3 text-sm text-info">Le fatture di acquisto vengono importate automaticamente dallo SDI.</p>
    @endif

    <div class="border-b border-border">
        <div class="flex gap-1 overflow-x-auto" role="tablist" aria-label="Filtra {{ strtolower($definition['plural']) }}">
            @foreach($definition['tabs'] as $tab)
                <button wire:click="selectTab('{{ $tab }}')" type="button" @class([
                    'inline-flex h-10 shrink-0 items-center border-b-2 px-3 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-primary/20',
                    'border-primary text-primary' => $this->isActive($tab),
                    'border-transparent text-content-muted hover:border-border hover:text-content' => ! $this->isActive($tab),
                ]) aria-pressed="{{ $this->isActive($tab) ? 'true' : 'false' }}">
                    {{ $this->tabLabel($tab) }}
                </button>
            @endforeach
        </div>
    </div>

    <div class="flex flex-col gap-3 border-b border-border pb-5 sm:flex-row sm:items-center">
        <div class="relative w-full sm:max-w-xl">
            <label for="document-search" class="sr-only">Cerca documenti</label>
            <svg class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-content-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <circle cx="11" cy="11" r="7" />
                <path d="m20 20-4-4" />
            </svg>
            <input id="document-search" wire:model.live.debounce.350ms="search" type="search" class="block h-11 w-full rounded-lg border border-border-strong bg-white py-2 pl-10 pr-3 text-sm text-content placeholder:text-text-muted focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" placeholder="Cerca per numero o {{ strtolower($definition['contact']) }}">
        </div>

        @if($search !== '' || $status !== '' || $payment !== '')
            <button wire:click="resetFilters" type="button" class="inline-flex h-11 items-center justify-center rounded-lg px-3 text-sm font-medium text-content-muted transition hover:bg-surface-muted hover:text-content focus:outline-none focus:ring-2 focus:ring-primary/20">
                Cancella filtri
            </button>
        @endif
    </div>

    <div class="overflow-x-auto border-y border-border bg-white">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-border bg-surface-muted text-content-muted">
                <tr>
                    @foreach (['number' => 'Numero', 'date' => 'Data'] as $column => $label)
                        @php
                            $isActiveSort = $sort === $column;
                            $ariaSort = $isActiveSort ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none';
                        @endphp
                        <th scope="col" aria-sort="{{ $ariaSort }}" class="px-5 py-3 text-xs font-medium">
                            <button wire:click="sortBy('{{ $column }}')" type="button" class="inline-flex min-h-6 items-center gap-1.5 rounded text-left transition hover:text-content focus:outline-none focus:ring-2 focus:ring-primary/20" aria-label="Ordina per {{ $label }}">
                                <span>{{ $label }}</span>
                                <x-icon :name="$isActiveSort ? ($direction === 'asc' ? 'o-chevron-up' : 'o-chevron-down') : 'o-chevron-up-down'" @class(['size-3.5', 'text-primary' => $isActiveSort, 'opacity-40' => ! $isActiveSort]) />
                            </button>
                        </th>
                    @endforeach
                    <th scope="col" class="px-5 py-3 text-xs font-medium">{{ $definition['contact'] }}</th>
                    @php
                        $isActiveSort = $sort === 'total_gross';
                        $ariaSort = $isActiveSort ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none';
                    @endphp
                    <th scope="col" aria-sort="{{ $ariaSort }}" class="px-5 py-3 text-right text-xs font-medium">
                        <button wire:click="sortBy('total_gross')" type="button" class="inline-flex min-h-6 items-center gap-1.5 rounded text-right transition hover:text-content focus:outline-none focus:ring-2 focus:ring-primary/20" aria-label="Ordina per imponibile">
                            <span>Imponibile</span>
                            <x-icon :name="$isActiveSort ? ($direction === 'asc' ? 'o-chevron-up' : 'o-chevron-down') : 'o-chevron-up-down'" @class(['size-3.5', 'text-primary' => $isActiveSort, 'opacity-40' => ! $isActiveSort]) />
                        </button>
                    </th>
                    <th scope="col" class="px-5 py-3 text-right text-xs font-medium">IVA</th>
                    <th scope="col" class="px-5 py-3 text-xs font-medium">Stato</th>
                    @if($this->hasPayments())
                        <th scope="col" class="px-5 py-3 text-xs font-medium">Pagamento</th>
                    @endif
                    <th scope="col" class="w-14 px-3 py-3 text-right text-xs font-medium"><span class="sr-only">Azioni</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse($documents as $document)
                    <tr class="transition-colors hover:bg-surface-muted/70 focus-within:bg-primary-subtle">
                        <td class="px-5 py-3.5 font-medium text-content">
                            <x-app-link href="/{{ $definition['base'] }}/{{ $document->id }}/edit" class="rounded text-content transition hover:text-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                                {{ $document->number ?? '#'.$document->id }}
                            </x-app-link>
                        </td>
                        <td class="px-5 py-3.5 text-content-muted">{{ \Carbon\Carbon::parse($document->date)->format('d/m/Y') }}</td>
                        <td class="px-5 py-3.5 text-content">{{ $document->contact?->name ?? '—' }}</td>
                        <td class="px-5 py-3.5 text-right tabular-nums">
                            <p class="font-medium text-content">{{ $this->money($document->total_gross - $document->total_vat) }}</p>
                            @if($this->hasPayments() && (int) $document->net_due !== (int) ($document->total_gross - $document->total_vat))
                                <p class="mt-0.5 text-xs text-content-muted">Da pagare {{ $this->money($document->net_due) }}</p>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-right font-medium tabular-nums text-content">{{ $this->money($document->total_vat) }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            <x-badge :value="$this->statusLabel($document->status)" :variant="$this->statusTone($document->status)" dot />
                        </td>
                        @if($this->hasPayments())
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <x-badge :value="$this->statusLabel($document->payment_status)" :variant="$this->statusTone($document->payment_status)" dot />
                            </td>
                        @endif
                        <td class="px-3 py-3.5 text-right"><x-documents.document-actions :document="$document" :type="$type" :base="$definition['base']" /></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $this->hasPayments() ? 8 : 7 }}" class="px-5 py-12 text-center text-sm text-content-muted">
                            {{ $search !== '' || $status !== '' || $payment !== '' ? 'Nessun documento corrisponde ai filtri.' : 'Nessuna '.$definition['singular'].' ancora registrata.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($documents->hasPages())
        <div class="pt-1">{{ $documents->links() }}</div>
    @endif
    <x-documents.document-action-center />
</section>
