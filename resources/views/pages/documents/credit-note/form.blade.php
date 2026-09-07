<?php

use App\Actions\SaveCreditNote;
use App\Enums\VatRate;
use App\Models\Contact;
use App\Models\CreditNote;
use App\Services\DocumentEventRecorder;
use App\Services\DocumentSequenceResolver;
use App\Services\PostHogTelemetryService;
use App\Settings\CompanySettings;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component {
    public ?CreditNote $invoice = null;

    public int|string $contact_id = '';

    /** @var array<int, array{id: int, name: string, subtitle: string|null}> */
    public array $contactOptions = [];

    public ?string $numberPreview = null;

    public string $date = '';

    public string $related_invoice_number = '';

    public string $related_invoice_date = '';

    public string $notes = '';

    public array $lines = [];

    public string $tab = 'data';

    public function mount(?CreditNote $creditNote = null): void
    {
        $this->invoice = $creditNote?->exists ? $creditNote->load(['lines', 'events' => fn ($query) => $query->latest('occurred_at')]) : null;
        $this->date = now()->toDateString();
        $this->contactOptions = Contact::query()->orderBy('name')->get(['id', 'name', 'vat_number'])
            ->map(fn (Contact $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'subtitle' => $c->vat_number ? 'P.IVA '.$c->vat_number : null,
            ])->toArray();
        if ($this->invoice) {
            foreach (['contact_id', 'related_invoice_number', 'notes'] as $field) {
                $this->{$field} = (string) ($this->invoice->{$field} ?? '');
            }
            $this->date = $this->invoice->date->toDateString();
            $this->related_invoice_date = $this->invoice->related_invoice_date?->toDateString() ?? '';
            $this->lines = $this->invoice->lines->map(fn ($line) => $this->state(['key' => (string) $line->id, 'description' => $line->description, 'quantity' => $line->quantity, 'unit_of_measure' => $line->unit_of_measure, 'unit_price' => $line->unit_price / 100, 'vat_rate' => $line->vat_rate->value]))->all();
        }
        $this->lines = $this->lines ?: [$this->emptyLine()];
        $this->refreshNumberPreview();
    }

    public function addLine(): void
    {
        $this->lines[] = $this->emptyLine();
        $this->dispatch('sales-line-added', key: $this->lines[array_key_last($this->lines)]['key']);
    }

    public function removeLine(int $index): void
    {
        if (count($this->lines) > 1) {
            array_splice($this->lines, $index, 1);
            $nextIndex = min($index, count($this->lines) - 1);
            $this->dispatch('sales-line-removed', key: $this->lines[$nextIndex]['key']);
        }
    }

    public function toggleLineDetails(int $index): void
    {
        $this->lines[$index]['details_enabled'] = ! ($this->lines[$index]['details_enabled'] ?? false);
    }

    public function updatedDate(): void
    {
        $this->refreshNumberPreview();
    }

    public function save(SaveCreditNote $saveCreditNote): mixed
    {
        if ($this->readOnly) {
            $this->addError('creditNote', 'Questa nota di credito non è più modificabile.');

            return null;
        }
        $saved = $this->invoice ? $saveCreditNote->update($this->invoice, $this->validate($this->rules())) : $saveCreditNote->create($this->validate($this->rules()));
        if (! $this->invoice) {
            app(DocumentEventRecorder::class)->created($saved);
            app(PostHogTelemetryService::class)->capture('credit_note_created', app(PostHogTelemetryService::class)->documentProperties($saved), auth()->user());
        }

        return $this->redirectRoute('credit-notes.index', navigate: true);
    }

    public function getReadOnlyProperty(): bool
    {
        return $this->invoice && (! $this->invoice->isSdiEditable() || $this->invoice->date->year < now()->year);
    }

    public function getNetTotalProperty(): float
    {
        return round(array_sum(array_map($this->lineTotal(...), $this->lines)), 2);
    }

    public function getVatTotalProperty(): float
    {
        return round(array_sum(array_map(fn ($line) => $this->lineTotal($line) * $this->vatPercent($line['vat_rate']) / 100, $this->lines)), 2);
    }

    public function getGrossTotalProperty(): float
    {
        return $this->netTotal + $this->vatTotal;
    }

    private function rules(): array
    {
        $rules = ['contact_id' => 'required|exists:contacts,id', 'date' => 'required|date', 'related_invoice_number' => 'nullable|string', 'related_invoice_date' => 'nullable|date', 'notes' => 'nullable|string', 'lines' => 'required|array|min:1', 'lines.*.description' => 'required|string', 'lines.*.quantity' => 'required|numeric|min:0.01', 'lines.*.unit_of_measure' => 'nullable|string', 'lines.*.unit_price' => 'required|numeric|min:0', 'lines.*.vat_rate' => 'required|string'];

        return $rules;
    }

    private function emptyLine(): array
    {
        return $this->state(['key' => (string) str()->uuid(), 'description' => '', 'quantity' => '1', 'unit_of_measure' => '', 'unit_price' => '0.00', 'vat_rate' => $this->isRf19() ? 'N2.2' : 'R22']);
    }

    private function refreshNumberPreview(): void
    {
        $this->numberPreview = $this->invoice?->number ?? app(DocumentSequenceResolver::class)->resolve('credit_note')->getFormattedNumber((int) substr($this->date, 0, 4));
    }

    private function state(array $line): array
    {
        return [...$line, 'quantity' => (string) $line['quantity'], 'unit_of_measure' => $line['unit_of_measure'] ?? '', 'unit_price' => number_format((float) $line['unit_price'], 2, '.', ''), 'details_enabled' => $line['quantity'] != 1 || ($line['unit_of_measure'] ?? '') !== ''];
    }

    public function isRf19(): bool
    {
        return app(CompanySettings::class)->company_fiscal_regime === 'RF19';
    }

    private function lineTotal(array $line): float
    {
        return max(0, (float) ($line['quantity'] ?: 0)) * max(0, (float) ($line['unit_price'] ?: 0));
    }

    private function vatPercent(string $rate): float
    {
        return VatRate::tryFrom($rate)?->percent() ?? 0;
    }
};
?>
<x-slot:header><div><p class="text-xs font-medium text-content-muted">Vendite</p><h1 class="text-lg font-semibold text-content">{{ $invoice ? 'Modifica nota di credito' : 'Nuova nota di credito' }}</h1></div></x-slot:header>
<section class="mx-auto max-w-7xl space-y-6 pb-24">
    @if($this->readOnly)<div class="rounded-md border border-warning/20 bg-warning-bg p-4 text-sm text-warning">Questa nota di credito non è più modificabile.</div>@endif
    @error('creditNote')<div class="rounded-md border border-danger/20 bg-danger-bg p-4 text-sm text-danger">{{ $message }}</div>@enderror
    <form wire:submit="save" x-data="{ dirty: false, tab: @entangle('tab'), tabs: ['data', 'notes' @if($invoice), 'history' @endif], selectTab(nextTab) { this.tab = nextTab; this.$nextTick(() => document.getElementById('credit-note-tab-' + nextTab)?.focus()); }, moveTab(step) { this.selectTab(this.tabs[(this.tabs.indexOf(this.tab) + step + this.tabs.length) % this.tabs.length]); } }" @beforeunload.window="if (dirty) { $event.preventDefault(); $event.returnValue = ''; }" @input="dirty = true" @change="dirty = true" @sales-line-added.window="$nextTick(() => document.getElementById('sales-line-' + $event.detail.key + '-description')?.focus())" @sales-line-removed.window="$nextTick(() => document.getElementById('sales-line-' + $event.detail.key + '-description')?.focus())" class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="space-y-6">
        <article class="rounded-xl border border-border bg-white p-5 sm:p-6">
            <x-documents.invoice-form.data-section variant="sales-editor">
                <div class="flex items-start justify-between gap-4"><div><h2 class="text-base font-semibold text-content">Dati nota di credito</h2><p class="mt-1 text-sm text-content-muted">Cliente, documento originario e condizioni della nota.</p></div><x-badge :value="$invoice?->status?->label() ?? 'Bozza'" variant="neutral" /></div>
                <div class="tabs tabs-border mt-5 border-b border-border" role="tablist" aria-label="Sezioni nota di credito">@foreach(['data' => 'Dati nota', 'notes' => 'Note'] as $key => $label)<button id="credit-note-tab-{{ $key }}" type="button" role="tab" @click="selectTab('{{ $key }}')" @keydown.right.prevent="moveTab(1)" @keydown.left.prevent="moveTab(-1)" @keydown.home.prevent="selectTab('data')" @keydown.end.prevent="selectTab(tabs[tabs.length - 1])" :aria-selected="tab === '{{ $key }}'" :tabindex="tab === '{{ $key }}' ? 0 : -1" aria-controls="credit-note-panel-{{ $key }}" :class="tab === '{{ $key }}' ? 'border-primary text-primary' : 'border-transparent text-content-muted hover:text-content'" class="-mb-px border-b-2 px-3 py-3 text-sm font-medium transition-colors">{{ $label }}</button>@endforeach @if($invoice)<button id="credit-note-tab-history" type="button" role="tab" @click="selectTab('history')" @keydown.right.prevent="moveTab(1)" @keydown.left.prevent="moveTab(-1)" @keydown.home.prevent="selectTab('data')" @keydown.end.prevent="selectTab(tabs[tabs.length - 1])" :aria-selected="tab === 'history'" :tabindex="tab === 'history' ? 0 : -1" aria-controls="credit-note-panel-history" :class="tab === 'history' ? 'border-primary text-primary' : 'border-transparent text-content-muted hover:text-content'" class="-mb-px border-b-2 px-3 py-3 text-sm font-medium transition-colors">Storico</button>@endif</div>
                <section id="credit-note-panel-data" role="tabpanel" aria-labelledby="credit-note-tab-data" class="pt-5" x-show="tab === 'data'"><x-documents.invoice-form.data-fields><label class="text-sm font-semibold">Cliente *<x-select wire:model="contact_id" :disabled="$this->readOnly" :options="$contactOptions" searchable searchPlaceholder="Cerca per nome o P.IVA" />@error('contact_id')<span class="text-xs text-danger">{{ $message }}</span>@enderror</label><div class="text-sm font-semibold">Numero<div class="mt-1 h-11 rounded-md border border-border-light bg-surface-muted px-3 py-3 text-sm font-normal">{{ $numberPreview ?? 'Configura il sezionale predefinito' }}</div></div><label class="text-sm font-semibold">Data *<input wire:model.live="date" type="date" @disabled($this->readOnly) class="mt-1 h-11 w-full rounded-md border border-border px-3 text-sm">@error('date')<span class="text-xs text-danger">{{ $message }}</span>@enderror</label><label class="text-sm font-semibold">Numero fattura originaria<input wire:model="related_invoice_number" @disabled($this->readOnly) class="mt-1 h-11 w-full rounded-md border border-border px-3 text-sm"></label><label class="text-sm font-semibold">Data fattura originaria<input wire:model="related_invoice_date" type="date" @disabled($this->readOnly) class="mt-1 h-11 w-full rounded-md border border-border px-3 text-sm"></label></x-documents.invoice-form.data-fields></section>
                <section id="credit-note-panel-notes" role="tabpanel" aria-labelledby="credit-note-tab-notes" x-show="tab === 'notes'" x-cloak class="pt-5"><label class="text-sm font-semibold">Note<textarea wire:model="notes" @disabled($this->readOnly) rows="5" class="mt-1 w-full rounded-md border border-border px-3 py-2 text-sm"></textarea></label></section>
                @if($invoice)<section id="credit-note-panel-history" role="tabpanel" aria-labelledby="credit-note-tab-history" x-show="tab === 'history'" x-cloak class="space-y-3 pt-5">@forelse($invoice->events as $event)<div class="border-l-2 border-primary pl-3"><p class="text-sm font-semibold">{{ $event->title }}</p><p class="text-xs text-content-muted">{{ $event->occurred_at?->format('d/m/Y H:i') }} {{ $event->message }}</p></div>@empty<p class="text-sm text-content-muted">Nessun evento registrato.</p>@endforelse</section>@endif
            </x-documents.invoice-form.data-section>
        </article>
            <x-documents.invoice-form.lines title="Righe nota di credito" :read-only="$this->readOnly">
                @foreach($lines as $index => $line)<x-documents.invoice-form.line :line="$line" :index="$index" :lines-count="count($lines)" :read-only="$this->readOnly" :line-total="$this->lineTotal($line)" :has-discount="false" :vat-disabled="$this->isRf19()" />@endforeach
            </x-documents.invoice-form.lines>
        </div>
        <aside class="space-y-4">
            <x-documents.invoice-form.totals :net-total="$this->netTotal" :vat-total="$this->vatTotal" :gross-total="$this->grossTotal" :net-due="$this->grossTotal" />
            <x-documents.invoice-form.action-bar variant="sales-editor" cancel-route="credit-notes.index" :submit-label="$invoice ? 'Aggiorna nota di credito' : 'Crea nota di credito'" :read-only="$this->readOnly" :net-due="$this->grossTotal" />
        </aside>
    </form>
</section>
