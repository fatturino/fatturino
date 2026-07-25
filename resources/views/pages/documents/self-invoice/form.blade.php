<?php

use App\Actions\SaveSelfInvoice;
use App\Enums\VatRate;
use App\Models\Contact;
use App\Models\SelfInvoice;
use App\Models\Sequence;
use App\Services\DocumentEventRecorder;
use App\Services\PostHogTelemetryService;
use App\Settings\CompanySettings;
use App\Support\FiscalRegimePolicy;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component {
    public ?SelfInvoice $invoice = null;
    public int|string $contact_id = '';
    public int|string $sequence_id = '';
    public string $number = '';
    public string $date = '';
    public string $due_date = '';
    public string $document_type = 'TD17';
    public string $related_invoice_number = '';
    public string $related_invoice_date = '';
    public string $notes = '';
    public array $lines = [];
    public string $tab = 'data';

    public function mount(?SelfInvoice $selfInvoice = null): void
    {
        $settings = app(CompanySettings::class);
        abort_unless(FiscalRegimePolicy::supportsSelfInvoices($settings->company_fiscal_regime, $settings->rf19_self_invoices_enabled), 403);

        $selfInvoice = $selfInvoice?->exists ? $selfInvoice : null;
        $this->invoice = $selfInvoice?->load(['lines', 'events' => fn($query) => $query->latest('occurred_at')]);
        $this->date = now()->toDateString();
        $this->sequence_id = Sequence::query()->where('type', 'self_invoice')->orderByDesc('is_system')->value('id') ?? '';

        if ($selfInvoice) {
            foreach (['contact_id', 'sequence_id', 'number', 'document_type', 'related_invoice_number', 'notes'] as $field) $this->{$field} = (string) ($selfInvoice->{$field} ?? '');
            $this->date = $selfInvoice->date->toDateString();
            $this->due_date = $selfInvoice->due_date?->toDateString() ?? '';
            $this->related_invoice_date = $selfInvoice->related_invoice_date?->toDateString() ?? '';
            $this->lines = $selfInvoice->lines->map(fn($line) => $this->lineState(['key' => (string) $line->id, 'description' => $line->description, 'quantity' => $line->quantity, 'unit_of_measure' => $line->unit_of_measure, 'unit_price' => $line->unit_price / 100, 'vat_rate' => $line->vat_rate->value]))->all();
        }

        $this->lines = $this->lines ?: [$this->emptyLine()];
    }

    public function addLine(): void { $this->lines[] = $this->emptyLine(); }
    public function removeLine(int $index): void { if (count($this->lines) > 1) array_splice($this->lines, $index, 1); }
    public function toggleLineDetails(int $index): void { $this->lines[$index]['details_enabled'] = ! ($this->lines[$index]['details_enabled'] ?? false); }

    public function save(SaveSelfInvoice $saveSelfInvoice): mixed
    {
        if ($this->readOnly) { $this->addError('invoice', 'Questa autofattura non è più modificabile.'); return null; }
        $payload = $this->validate($this->rules());
        $saved = $this->invoice ? $saveSelfInvoice->update($this->invoice, $payload) : $saveSelfInvoice->create($payload);

        if (! $this->invoice) {
            app(DocumentEventRecorder::class)->created($saved);
            app(PostHogTelemetryService::class)->capture('self_invoice_created', app(PostHogTelemetryService::class)->documentProperties($saved), auth()->user());
        }

        session()->flash('success', $this->invoice ? 'Autofattura aggiornata.' : 'Autofattura creata.');
        return $this->redirectRoute('self-invoices.index', navigate: true);
    }

    public function getReadOnlyProperty(): bool { return $this->invoice && (! $this->invoice->isSdiEditable() || $this->invoice->date->year < now()->year); }
    public function getNetTotalProperty(): float { return round(array_sum(array_map($this->lineTotal(...), $this->lines)), 2); }
    public function getVatTotalProperty(): float { return round(array_sum(array_map(fn($line) => $this->lineTotal($line) * $this->vatPercent($line['vat_rate'] ?? '') / 100, $this->lines)), 2); }
    public function getGrossTotalProperty(): float { return $this->netTotal + $this->vatTotal; }
    private function rules(): array { $rules = ['contact_id' => 'required|exists:contacts,id', 'date' => 'required|date', 'due_date' => 'nullable|date', 'document_type' => 'required|in:TD17,TD18,TD19,TD28,TD29', 'related_invoice_number' => 'nullable|string|max:20', 'related_invoice_date' => 'nullable|date', 'notes' => 'nullable|string', 'lines' => 'required|array|min:1', 'lines.*.description' => 'required|string', 'lines.*.quantity' => 'required|numeric|min:0.01', 'lines.*.unit_of_measure' => 'nullable|string', 'lines.*.unit_price' => 'required|numeric|min:0', 'lines.*.vat_rate' => 'required|string']; if (! $this->invoice) { $rules['sequence_id'] = 'required|exists:sequences,id'; $rules['number'] = 'nullable|string'; } return $rules; }
    private function emptyLine(): array { return $this->lineState(['key' => (string) str()->uuid(), 'description' => '', 'quantity' => '1', 'unit_of_measure' => '', 'unit_price' => '0.00', 'vat_rate' => 'R22']); }
    private function lineState(array $line): array { return [...$line, 'quantity' => (string) $line['quantity'], 'unit_of_measure' => $line['unit_of_measure'] ?? '', 'unit_price' => number_format((float) $line['unit_price'], 2, '.', ''), 'details_enabled' => $line['quantity'] != 1 || ($line['unit_of_measure'] ?? '') !== '']; }
    private function lineTotal(array $line): float { return max(0, (float) ($line['quantity'] ?: 0)) * max(0, (float) ($line['unit_price'] ?: 0)); }
    private function vatPercent(string $value): float { return VatRate::tryFrom($value)?->percent() ?? 0; }
}; ?>

<x-slot:header><div><p class="text-xs font-bold uppercase tracking-[.12em] text-content-muted">Documenti</p><h1 class="text-lg font-bold text-content">{{ $invoice ? 'Modifica autofattura' : 'Nuova autofattura' }}</h1></div></x-slot:header>
<section class="mx-auto max-w-7xl space-y-6 pb-24">
    @if(session('success'))<div class="rounded-md border border-success/20 bg-success-bg p-4 text-sm text-success">{{ session('success') }}</div>@endif
    @if($this->readOnly)<div class="rounded-md border border-warning/20 bg-warning-bg p-4 text-sm text-warning">Questa autofattura non è più modificabile.</div>@endif
    @error('invoice')<div class="rounded-md border border-danger/20 bg-danger-bg p-4 text-sm text-danger">{{ $message }}</div>@enderror
    <form wire:submit="save" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="space-y-6">
            <article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]">
                <nav class="mb-5 flex gap-2 border-b border-border-light pb-4">@foreach(['data' => 'Dati', 'notes' => 'Note'] as $key => $label)<button type="button" wire:click="$set('tab', '{{ $key }}')" class="rounded-md px-3 py-2 text-sm font-semibold {{ $tab === $key ? 'bg-primary text-white' : 'text-content-muted' }}">{{ $label }}</button>@endforeach @if($invoice)<button type="button" wire:click="$set('tab', 'history')" class="rounded-md px-3 py-2 text-sm font-semibold {{ $tab === 'history' ? 'bg-primary text-white' : 'text-content-muted' }}">Storico</button>@endif</nav>
                @if($tab === 'data')<div class="grid gap-4 sm:grid-cols-2"><label class="text-sm font-semibold">Fornitore *<select wire:model="contact_id" @disabled($this->readOnly) class="mt-1 h-11 w-full rounded-md border border-border bg-white px-3 text-sm"><option value="">Seleziona fornitore...</option>@foreach(Contact::orderBy('name')->get(['id', 'name']) as $contact)<option value="{{ $contact->id }}">{{ $contact->name }}</option>@endforeach</select>@error('contact_id')<span class="text-xs text-danger">{{ $message }}</span>@enderror</label><label class="text-sm font-semibold">Tipo documento *<select wire:model="document_type" @disabled($this->readOnly) class="mt-1 h-11 w-full rounded-md border border-border bg-white px-3 text-sm">@foreach(['TD17' => 'Acquisto servizi dall’estero', 'TD18' => 'Acquisto beni intracomunitari', 'TD19' => 'Acquisto beni ex art.17', 'TD28' => 'San Marino con IVA', 'TD29' => 'Omessa/irregolare fatturazione'] as $value => $label)<option value="{{ $value }}">{{ $value }} - {{ $label }}</option>@endforeach</select></label>@unless($invoice)<label class="text-sm font-semibold">Sequenza *<select wire:model="sequence_id" @disabled($this->readOnly) class="mt-1 h-11 w-full rounded-md border border-border bg-white px-3 text-sm"><option value="">Seleziona sequenza...</option>@foreach(Sequence::query()->where('type', 'self_invoice')->get(['id', 'name']) as $sequence)<option value="{{ $sequence->id }}">{{ $sequence->name }}</option>@endforeach</select></label><label class="text-sm font-semibold">Numero manuale (opzionale)<input wire:model="number" @disabled($this->readOnly) class="mt-1 h-11 w-full rounded-md border border-border px-3 text-sm"></label>@endunless<label class="text-sm font-semibold">Data *<input wire:model.live="date" type="date" @disabled($this->readOnly) class="mt-1 h-11 w-full rounded-md border border-border px-3 text-sm"></label><label class="text-sm font-semibold">Scadenza<input wire:model="due_date" type="date" @disabled($this->readOnly) class="mt-1 h-11 w-full rounded-md border border-border px-3 text-sm"></label><label class="text-sm font-semibold">Numero fattura collegata<input wire:model="related_invoice_number" @disabled($this->readOnly) class="mt-1 h-11 w-full rounded-md border border-border px-3 text-sm"></label><label class="text-sm font-semibold">Data fattura collegata<input wire:model="related_invoice_date" type="date" @disabled($this->readOnly) class="mt-1 h-11 w-full rounded-md border border-border px-3 text-sm"></label></div>
                @elseif($tab === 'notes')<label class="text-sm font-semibold">Note<textarea wire:model="notes" @disabled($this->readOnly) rows="5" class="mt-1 w-full rounded-md border border-border px-3 py-2 text-sm"></textarea></label>
                @else<div class="space-y-3">@forelse($invoice->events as $event)<div class="border-l-2 border-primary pl-3"><p class="text-sm font-semibold">{{ $event->title }}</p><p class="text-xs text-content-muted">{{ $event->occurred_at?->format('d/m/Y H:i') }} {{ $event->message }}</p></div>@empty<p class="text-sm text-content-muted">Nessun evento registrato.</p>@endforelse</div>@endif
            </article>
            <x-documents.invoice-form.lines title="Righe autofattura" :read-only="$this->readOnly">
                @foreach($lines as $index => $line)
                    <x-documents.invoice-form.line :line="$line" :index="$index" :lines-count="count($lines)" :read-only="$this->readOnly" :line-total="$this->lineTotal($line)" :has-discount="false" :vat-disabled="false" />
                @endforeach
            </x-documents.invoice-form.lines>
        </div>
        <aside class="space-y-4">
            <x-documents.invoice-form.totals :net-total="$this->netTotal" :vat-total="$this->vatTotal" :gross-total="$this->grossTotal" note="L'autofattura viene registrata come saldata alla data di emissione." />
        </aside>
        <x-documents.invoice-form.action-bar cancel-route="self-invoices.index" :submit-label="$invoice ? 'Aggiorna autofattura' : 'Crea autofattura'" :read-only="$this->readOnly" />
    </form>
</section>
