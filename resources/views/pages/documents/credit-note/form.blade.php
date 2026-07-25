<?php

use App\Actions\SaveCreditNote;
use App\Enums\VatRate;
use App\Models\Contact;
use App\Models\CreditNote;
use App\Models\Sequence;
use App\Services\DocumentEventRecorder;
use App\Services\PostHogTelemetryService;
use App\Settings\CompanySettings;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component {
    public ?CreditNote $invoice = null;
    public int|string $contact_id = '';
    public int|string $sequence_id = '';
    public string $date = '';
    public string $related_invoice_number = '';
    public string $related_invoice_date = '';
    public string $notes = '';
    public array $lines = [];
    public string $tab = 'data';

    public function mount(?CreditNote $creditNote = null): void
    {
        $this->invoice = $creditNote?->exists ? $creditNote->load(['lines', 'events' => fn($query) => $query->latest('occurred_at')]) : null;
        $this->date = now()->toDateString();
        $this->sequence_id = Sequence::query()->where('type', 'credit_note')->orderByDesc('is_system')->value('id') ?? '';
        if ($this->invoice) {
            foreach (['contact_id', 'sequence_id', 'related_invoice_number', 'notes'] as $field) $this->{$field} = (string) ($this->invoice->{$field} ?? '');
            $this->date = $this->invoice->date->toDateString();
            $this->related_invoice_date = $this->invoice->related_invoice_date?->toDateString() ?? '';
            $this->lines = $this->invoice->lines->map(fn($line) => $this->state(['key' => (string) $line->id, 'description' => $line->description, 'quantity' => $line->quantity, 'unit_of_measure' => $line->unit_of_measure, 'unit_price' => $line->unit_price / 100, 'vat_rate' => $line->vat_rate->value]))->all();
        }
        $this->lines = $this->lines ?: [$this->emptyLine()];
    }
    public function addLine(): void { $this->lines[] = $this->emptyLine(); }
    public function removeLine(int $index): void { if (count($this->lines) > 1) array_splice($this->lines, $index, 1); }
    public function toggleLineDetails(int $index): void { $this->lines[$index]['details_enabled'] = ! ($this->lines[$index]['details_enabled'] ?? false); }
    public function save(SaveCreditNote $saveCreditNote): mixed { if ($this->readOnly) { $this->addError('creditNote', 'Questa nota di credito non è più modificabile.'); return null; } $saved = $this->invoice ? $saveCreditNote->update($this->invoice, $this->validate($this->rules())) : $saveCreditNote->create($this->validate($this->rules())); if (! $this->invoice) { app(DocumentEventRecorder::class)->created($saved); app(PostHogTelemetryService::class)->capture('credit_note_created', app(PostHogTelemetryService::class)->documentProperties($saved), auth()->user()); } return $this->redirectRoute('credit-notes.index', navigate: true); }
    public function getReadOnlyProperty(): bool { return $this->invoice && (! $this->invoice->isSdiEditable() || $this->invoice->date->year < now()->year); }
    public function getNetTotalProperty(): float { return round(array_sum(array_map($this->lineTotal(...), $this->lines)), 2); }
    public function getVatTotalProperty(): float { return round(array_sum(array_map(fn($line) => $this->lineTotal($line) * $this->vatPercent($line['vat_rate']) / 100, $this->lines)), 2); }
    public function getGrossTotalProperty(): float { return $this->netTotal + $this->vatTotal; }
    private function rules(): array { $rules = ['contact_id' => 'required|exists:contacts,id', 'date' => 'required|date', 'related_invoice_number' => 'nullable|string', 'related_invoice_date' => 'nullable|date', 'notes' => 'nullable|string', 'lines' => 'required|array|min:1', 'lines.*.description' => 'required|string', 'lines.*.quantity' => 'required|numeric|min:0.01', 'lines.*.unit_of_measure' => 'nullable|string', 'lines.*.unit_price' => 'required|numeric|min:0', 'lines.*.vat_rate' => 'required|string']; if (! $this->invoice) $rules['sequence_id'] = 'required|exists:sequences,id'; return $rules; }
    private function emptyLine(): array { return $this->state(['key' => (string) str()->uuid(), 'description' => '', 'quantity' => '1', 'unit_of_measure' => '', 'unit_price' => '0.00', 'vat_rate' => $this->isRf19() ? 'N2.2' : 'R22']); }
    private function state(array $line): array { return [...$line, 'quantity' => (string) $line['quantity'], 'unit_of_measure' => $line['unit_of_measure'] ?? '', 'unit_price' => number_format((float) $line['unit_price'], 2, '.', ''), 'details_enabled' => $line['quantity'] != 1 || ($line['unit_of_measure'] ?? '') !== '']; }
    public function isRf19(): bool { return app(CompanySettings::class)->company_fiscal_regime === 'RF19'; }
    private function lineTotal(array $line): float { return max(0, (float) ($line['quantity'] ?: 0)) * max(0, (float) ($line['unit_price'] ?: 0)); }
    private function vatPercent(string $rate): float { return VatRate::tryFrom($rate)?->percent() ?? 0; }
}; ?>
<x-slot:header><div><p class="text-xs font-bold uppercase tracking-[.12em] text-content-muted">Vendite</p><h1 class="text-lg font-bold text-content">{{ $invoice ? 'Modifica nota di credito' : 'Nuova nota di credito' }}</h1></div></x-slot:header>
<section class="mx-auto max-w-7xl space-y-6 pb-24">@if($this->readOnly)<div class="rounded-md border border-warning/20 bg-warning-bg p-4 text-sm text-warning">Questa nota di credito non è più modificabile.</div>@endif @error('creditNote')<div class="rounded-md border border-danger/20 bg-danger-bg p-4 text-sm text-danger">{{ $message }}</div>@enderror
<form wire:submit="save" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]"><div class="space-y-6"><article class="rounded-xl border border-border-light bg-white p-5"><nav class="mb-5 flex gap-2 border-b border-border-light pb-4">@foreach(['data'=>'Dati','notes'=>'Note'] as $key=>$label)<button type="button" wire:click="$set('tab','{{ $key }}')" class="rounded-md px-3 py-2 text-sm font-semibold {{ $tab===$key?'bg-primary text-white':'text-content-muted' }}">{{ $label }}</button>@endforeach @if($invoice)<button type="button" wire:click="$set('tab','history')" class="rounded-md px-3 py-2 text-sm font-semibold">Storico</button>@endif</nav>@if($tab==='data')<div class="grid gap-4 sm:grid-cols-2"><label>Cliente *<select wire:model="contact_id" @disabled($this->readOnly) class="mt-1 h-11 w-full rounded-md border p-2"><option value="">Seleziona cliente...</option>@foreach(Contact::orderBy('name')->get(['id','name']) as $contact)<option value="{{ $contact->id }}">{{ $contact->name }}</option>@endforeach</select></label>@unless($invoice)<label>Sequenza *<select wire:model="sequence_id" class="mt-1 h-11 w-full rounded-md border p-2">@foreach(Sequence::query()->where('type','credit_note')->get(['id','name']) as $sequence)<option value="{{ $sequence->id }}">{{ $sequence->name }}</option>@endforeach</select></label>@endunless<label>Data *<input wire:model="date" type="date" @disabled($this->readOnly) class="mt-1 h-11 w-full rounded-md border p-2"></label><label>Numero fattura originaria<input wire:model="related_invoice_number" @disabled($this->readOnly) class="mt-1 h-11 w-full rounded-md border p-2"></label><label>Data fattura originaria<input wire:model="related_invoice_date" type="date" @disabled($this->readOnly) class="mt-1 h-11 w-full rounded-md border p-2"></label></div>@elseif($tab==='notes')<textarea wire:model="notes" @disabled($this->readOnly) rows="5" class="w-full rounded-md border p-2"></textarea>@else<div class="space-y-3">@forelse($invoice->events as $event)<p>{{ $event->title }} · {{ $event->occurred_at?->format('d/m/Y H:i') }}</p>@empty<p>Nessun evento registrato.</p>@endforelse</div>@endif</article><article class="rounded-xl border border-border-light bg-white p-5"><div class="mb-4 flex justify-between"><h2 class="font-bold">Righe nota di credito</h2>@unless($this->readOnly)<button type="button" wire:click="addLine" class="text-primary">Aggiungi riga</button>@endunless</div>@foreach($lines as $index=>$line)<div wire:key="line-{{ $line['key'] }}" class="mb-3 rounded-md border p-3"><div class="grid gap-2 sm:grid-cols-3"><input wire:model="lines.{{ $index }}.description" @disabled($this->readOnly) placeholder="Descrizione" class="h-10 rounded border p-2"><input wire:model.live="lines.{{ $index }}.unit_price" @disabled($this->readOnly) type="number" step="0.01" class="h-10 rounded border p-2"><select wire:model="lines.{{ $index }}.vat_rate" @disabled($this->readOnly || $this->isRf19()) class="h-10 rounded border p-2">@foreach(VatRate::options() as $rate)<option value="{{ $rate['id'] }}">{{ $rate['name'] }}</option>@endforeach</select></div>@if($line['details_enabled'])<div class="mt-2 grid grid-cols-2 gap-2"><input wire:model="lines.{{ $index }}.quantity" type="number" step="0.01" class="h-10 rounded border p-2"><input wire:model="lines.{{ $index }}.unit_of_measure" class="h-10 rounded border p-2"></div>@endif @unless($this->readOnly)<button type="button" wire:click="toggleLineDetails({{ $index }})" class="mt-2 text-primary">Dettagli</button><button type="button" wire:click="removeLine({{ $index }})" class="mt-2 ml-3 text-danger">Rimuovi</button>@endunless</div>@endforeach</article></div><aside><article class="sticky top-20 rounded-xl border border-border-light bg-white p-5"><h2 class="font-bold">Riepilogo</h2><div class="mt-4 space-y-2"><p>Netto <strong class="float-right">€ {{ number_format($this->netTotal,2,',','.') }}</strong></p><p>IVA <strong class="float-right">€ {{ number_format($this->vatTotal,2,',','.') }}</strong></p><p class="border-t pt-2">Totale <strong class="float-right">€ {{ number_format($this->grossTotal,2,',','.') }}</strong></p></div></article></aside>@unless($this->readOnly)<div class="fixed inset-x-0 bottom-0 border-t bg-white p-3 text-right"><a href="{{ route('credit-notes.index') }}" class="mr-3 rounded border px-4 py-2">Annulla</a><button class="rounded bg-primary px-4 py-2 text-white">{{ $invoice?'Aggiorna nota di credito':'Crea nota di credito' }}</button></div>@endunless</form></section>
