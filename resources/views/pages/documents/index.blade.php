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

    /** @var array<int, int> */
    public array $selected = [];

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
        $this->selected = [];
        $this->resetPage();
    }

    /** @param array<int, int> $documentIds */
    public function togglePageSelection(array $documentIds): void
    {
        $documentIds = array_map('intval', $documentIds);
        $selectedIds = array_map('intval', $this->selected);
        $allSelected = $documentIds !== [] && count(array_intersect($documentIds, $selectedIds)) === count($documentIds);
        $this->selected = $allSelected
            ? array_values(array_diff($selectedIds, $documentIds))
                : array_values(array_unique([...$selectedIds, ...$documentIds]));
    }

    public function updatingPage(): void
    {
        $this->selected = [];
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

        $this->selected = array_values(array_diff($this->selected, [$proformaId]));

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
        $gross = (int) $aggregates->gross;
        $drafts = (int) $aggregates->drafts;
        $sent = (int) $aggregates->sent;
        $open = (int) ($aggregates->open ?? 0);
        $overdue = (int) ($aggregates->overdue ?? 0);

        return view('pages::documents.index', compact('documents', 'total', 'gross', 'drafts', 'sent', 'open', 'overdue'));
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
            'draft' => 'Bozza', 'xml_validated' => 'Validata', 'sent' => 'Inviata', 'converted' => 'Convertita', 'paid' => 'Pagata', 'partial' => 'Parziale', 'unpaid' => 'Da pagare', 'overdue' => 'Scaduta', default => $this->statusValue($value)
        };
    }

    public function statusClass(mixed $value): string
    {
        return match ($this->statusValue($value)) {
            'paid', 'sent', 'converted', 'delivered' => 'badge-sent', 'overdue', 'rejected', 'cancelled' => 'badge-overdue', 'draft', 'unpaid' => 'badge-draft', default => 'badge-neutral'
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
            $query->where('payment_status', $this->payment);
        }

        return $query;
    }

    private function aggregates(): object
    {
        $select = [
            'count(*) as total',
            'coalesce(sum(total_gross), 0) as gross',
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

<x-slot:header><div><p class="text-xs font-bold uppercase tracking-[.12em] text-content-muted">Documenti</p><h1 class="text-lg font-bold text-content">{{ $this->title() }}</h1></div></x-slot:header>

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
@endphp
<section
    class="space-y-6"
    x-data="documentActionCenter(@js($actionConfig))"
    x-on:document-action.window="handleAction($event.detail)"
>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-xs font-bold uppercase tracking-[.12em] text-content-muted">Anno fiscale {{ $fiscalYear }}</p><p class="mt-1 text-sm text-content-muted">Gestisci e monitora i tuoi documenti.</p></div>@if($definition['create'])<x-app-link href="/{{ $definition['base'] }}/create" class="rounded-md bg-primary px-4 py-2 text-sm font-bold text-white">{{ $definition['create'] }}</x-app-link>@endif</div>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><x-dashboard.kpi-card label="Totale" :value="$this->money($gross)" :detail="$total.' '.$definition['plural']"/><x-dashboard.kpi-card label="Valore medio" :value="$total ? $this->money($gross / $total) : '—'" detail="per documento"/><x-dashboard.kpi-card :label="$this->hasPayments() ? 'Da pagare' : 'Bozze'" :value="$this->hasPayments() ? $open : $drafts" :detail="$this->hasPayments() ? 'documenti aperti' : 'da completare'"/><x-dashboard.kpi-card :label="$this->hasPayments() ? 'Scadute' : 'Inviate'" :value="$this->hasPayments() ? $overdue : $sent" :detail="$this->hasPayments() ? 'da saldare' : 'documenti inviati'"/></div>
    @if($type === 'purchase')<p class="rounded-lg border border-primary/20 bg-primary/5 p-3 text-sm">Le fatture di acquisto vengono importate automaticamente dallo SDI.</p>@endif
    <article class="overflow-hidden rounded-xl border border-border-light bg-white shadow-[var(--shadow-card)]"><div class="border-b border-border-light p-4"><div class="flex flex-wrap gap-2">@foreach($definition['tabs'] as $tab)<button wire:click="selectTab('{{ $tab }}')" @class(['rounded-md px-3 py-2 text-sm font-semibold transition', 'bg-primary text-white' => $this->isActive($tab), 'bg-surface-muted text-content-muted hover:text-content' => !$this->isActive($tab)])>{{ $this->tabLabel($tab) }}</button>@endforeach</div><div class="mt-4 flex flex-col gap-3 sm:flex-row"><div class="relative w-full"><label for="document-search" class="sr-only">Cerca documenti</label><svg class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-content-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input id="document-search" wire:model.live.debounce.350ms="search" type="search" class="block h-11 w-full rounded-md border border-border bg-white py-2 pl-10 pr-3 text-sm text-content shadow-sm placeholder:text-content-muted focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" placeholder="Cerca per numero o {{ strtolower($definition['contact']) }}"></div><button wire:click="resetFilters" class="h-11 rounded-md border border-border bg-white px-4 py-2 text-sm font-semibold text-content shadow-sm hover:bg-surface-muted">Reset</button></div></div>
        <div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-surface-muted text-xs uppercase tracking-wide text-content-muted"><tr><th class="w-12 px-5 py-3 text-center"><label for="select-page" class="sr-only">Seleziona tutti i documenti nella pagina</label><input id="select-page" wire:click="togglePageSelection({{ \Illuminate\Support\Js::from($documents->pluck('id')->all()) }})" type="checkbox" @checked($documents->isNotEmpty() && $documents->every(fn ($document) => in_array($document->id, $selected, true))) class="size-4 rounded border-border text-primary focus:ring-2 focus:ring-primary/20"></th><th class="px-5 py-3"><button wire:click="sortBy('number')">Numero</button></th><th class="px-5 py-3"><button wire:click="sortBy('date')">Data</button></th><th class="px-5 py-3">{{ $definition['contact'] }}</th><th class="px-5 py-3 text-right"><button wire:click="sortBy('total_gross')">Totale</button></th><th class="px-5 py-3">Stato</th>@if($this->hasPayments())<th class="px-5 py-3">Pagamento</th>@endif<th class="px-5 py-3 text-right">Azioni</th></tr></thead><tbody class="divide-y divide-border-light">@forelse($documents as $document)<tr class="hover:bg-surface-muted/60"><td class="px-5 py-4 text-center"><label for="document-{{ $document->id }}" class="sr-only">Seleziona {{ $document->number ?? 'documento #'.$document->id }}</label><input id="document-{{ $document->id }}" wire:model.live="selected" type="checkbox" value="{{ $document->id }}" class="size-4 rounded border-border text-primary focus:ring-2 focus:ring-primary/20"></td><td class="px-5 py-4 font-semibold">{{ $document->number ?? '#'.$document->id }}</td><td class="px-5 py-4 text-content-muted">{{ \Carbon\Carbon::parse($document->date)->format('d/m/Y') }}</td><td class="px-5 py-4">{{ $document->contact?->name ?? '—' }}</td><td class="px-5 py-4 text-right font-bold tabular-nums">{{ $this->money($document->total_gross) }}</td><td class="px-5 py-4"><span class="badge {{ $this->statusClass($document->status) }}">{{ $this->statusLabel($document->status) }}</span></td>@if($this->hasPayments())<td class="px-5 py-4"><span class="badge {{ $this->statusClass($document->payment_status) }}">{{ $this->statusLabel($document->payment_status) }}</span></td>@endif<td class="px-5 py-4 text-right"><x-documents.document-actions :document="$document" :type="$type" :base="$definition['base']" /></td></tr>@empty<tr><td colspan="{{ $this->hasPayments() ? 8 : 7 }}" class="px-5 py-12 text-center text-sm text-content-muted">Nessuna {{ $definition['singular'] }} trovata.</td></tr>@endforelse</tbody></table></div>
        @if($documents->hasPages())<div class="border-t border-border-light px-5 py-4">{{ $documents->links() }}</div>@endif
    </article>
    <x-documents.document-action-center />
</section>
