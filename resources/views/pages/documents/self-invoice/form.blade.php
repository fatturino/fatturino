<?php

use App\Actions\SaveSelfInvoice;
use App\Enums\VatRate;
use App\Models\Contact;
use App\Models\SelfInvoice;
use App\Services\DocumentEventRecorder;
use App\Services\DocumentSequenceResolver;
use App\Services\PostHogTelemetryService;
use App\Settings\CompanySettings;
use App\Support\FiscalRegimePolicy;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component {
    public ?SelfInvoice $invoice = null;

    public int|string $contact_id = '';

    /** @var array<int, array{id: int, name: string, subtitle: string|null}> */
    public array $contactOptions = [];

    public ?string $numberPreview = null;

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
        $this->invoice = $selfInvoice?->load(['lines', 'events' => fn ($query) => $query->latest('occurred_at')]);
        $this->date = now()->toDateString();
        $this->contactOptions = Contact::query()->orderBy('name')->get(['id', 'name', 'vat_number'])
            ->map(fn (Contact $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'subtitle' => $c->vat_number ? 'P.IVA '.$c->vat_number : null,
            ])->toArray();
        if ($selfInvoice) {
            foreach (['contact_id', 'document_type', 'related_invoice_number', 'notes'] as $field) {
                $this->{$field} = (string) ($selfInvoice->{$field} ?? '');
            }
            $this->date = $selfInvoice->date->toDateString();
            $this->due_date = $selfInvoice->due_date?->toDateString() ?? '';
            $this->related_invoice_date = $selfInvoice->related_invoice_date?->toDateString() ?? '';
            $this->lines = $selfInvoice->lines->map(fn ($line) => $this->lineState(['key' => (string) $line->id, 'description' => $line->description, 'quantity' => $line->quantity, 'unit_of_measure' => $line->unit_of_measure, 'unit_price' => $line->unit_price / 100, 'vat_rate' => $line->vat_rate->value]))->all();
        }
        $this->lines = $this->lines ?: [$this->emptyLine()];
        $this->refreshNumberPreview();
    }

    public function addLine(): void
    {
        $this->lines[] = $this->emptyLine();
    }

    public function removeLine(int $index): void
    {
        if (count($this->lines) > 1) {
            array_splice($this->lines, $index, 1);
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

    public function save(SaveSelfInvoice $saveSelfInvoice): mixed
    {
        if ($this->readOnly) {
            $this->addError('invoice', 'Questa autofattura non è più modificabile.');

            return null;
        }
        $payload = $this->validate($this->rules());
        $saved = $this->invoice ? $saveSelfInvoice->update($this->invoice, $payload) : $saveSelfInvoice->create($payload);
        if (! $this->invoice) {
            app(DocumentEventRecorder::class)->created($saved);
            app(PostHogTelemetryService::class)->capture('self_invoice_created', app(PostHogTelemetryService::class)->documentProperties($saved), auth()->user());
        }
        session()->flash('success', $this->invoice ? 'Autofattura aggiornata.' : 'Autofattura creata.');

        return $this->redirectRoute('self-invoices.index', navigate: true);
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
        return round(array_sum(array_map(fn ($line) => $this->lineTotal($line) * $this->vatPercent($line['vat_rate'] ?? '') / 100, $this->lines)), 2);
    }

    public function getGrossTotalProperty(): float
    {
        return $this->netTotal + $this->vatTotal;
    }

    private function rules(): array
    {
        $rules = ['contact_id' => 'required|exists:contacts,id', 'date' => 'required|date', 'due_date' => 'nullable|date', 'document_type' => 'required|in:TD17,TD18,TD19,TD28,TD29', 'related_invoice_number' => 'nullable|string|max:20', 'related_invoice_date' => 'nullable|date', 'notes' => 'nullable|string', 'lines' => 'required|array|min:1', 'lines.*.description' => 'required|string', 'lines.*.quantity' => 'required|numeric|min:0.01', 'lines.*.unit_of_measure' => 'nullable|string', 'lines.*.unit_price' => 'required|numeric|min:0', 'lines.*.vat_rate' => 'required|string'];

        return $rules;
    }

    private function emptyLine(): array
    {
        return $this->lineState(['key' => (string) str()->uuid(), 'description' => '', 'quantity' => '1', 'unit_of_measure' => '', 'unit_price' => '0.00', 'vat_rate' => 'R22']);
    }

    private function refreshNumberPreview(): void
    {
        $this->numberPreview = $this->invoice?->number ?? app(DocumentSequenceResolver::class)->resolve('self_invoice')->getFormattedNumber((int) substr($this->date, 0, 4));
    }

    private function lineState(array $line): array
    {
        return [...$line, 'quantity' => (string) $line['quantity'], 'unit_of_measure' => $line['unit_of_measure'] ?? '', 'unit_price' => number_format((float) $line['unit_price'], 2, '.', ''), 'details_enabled' => $line['quantity'] != 1 || ($line['unit_of_measure'] ?? '') !== ''];
    }

    private function lineTotal(array $line): float
    {
        return max(0, (float) ($line['quantity'] ?: 0)) * max(0, (float) ($line['unit_price'] ?: 0));
    }

    private function vatPercent(string $value): float
    {
        return VatRate::tryFrom($value)?->percent() ?? 0;
    }
};
?>

<x-slot:header><div><p class="text-xs font-medium text-content-muted">Documenti</p><h1 class="text-lg font-semibold text-content">{{ $invoice ? 'Modifica autofattura' : 'Nuova autofattura' }}</h1></div></x-slot:header>
@php($openNotes = filled($notes))
<section class="mx-auto max-w-7xl space-y-6 pb-24">
    @if(session('success'))<div class="rounded-md border border-success/20 bg-success-bg p-4 text-sm text-success">{{ session('success') }}</div>@endif
    @if($this->readOnly)<div class="rounded-md border border-warning/20 bg-warning-bg p-4 text-sm text-warning">Questa autofattura non è più modificabile.</div>@endif
    @error('invoice')<div class="rounded-md border border-danger/20 bg-danger-bg p-4 text-sm text-danger">{{ $message }}</div>@enderror
    <form wire:submit="save" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <article class="rounded-xl border border-border bg-white p-5 sm:p-6">
            <x-documents.invoice-form.data-section variant="editor">
                <div class="flex items-start justify-between gap-4"><div><h2 class="text-base font-semibold text-content">Dati autofattura</h2><p class="mt-1 text-sm text-content-muted">Fornitore, tipo documento e riferimenti della fattura collegata.</p></div><span class="inline-flex items-center gap-2 text-xs font-medium text-content-muted"><span class="size-1.5 rounded-full bg-primary"></span>{{ $invoice?->status?->label() ?? 'Bozza' }}</span></div>
                <x-documents.invoice-form.data-fields variant="editor" class="mt-5">
                    <x-select label="Fornitore *" wire:model="contact_id" :disabled="$this->readOnly" :options="$contactOptions" searchable searchPlaceholder="Cerca per nome o P.IVA" placeholder="Seleziona fornitore..." />
                    <label>Numero<div class="mt-1 flex h-11 items-center rounded-lg border border-border-strong bg-surface-muted px-3 text-sm text-content-muted">{{ $numberPreview ?? 'Configura il sezionale predefinito' }}</div></label>
                    <x-select label="Tipo documento *" wire:model="document_type" :disabled="$this->readOnly" :options="['TD17' => 'TD17 - Acquisto servizi dall’estero', 'TD18' => 'TD18 - Acquisto beni intracomunitari', 'TD19' => 'TD19 - Acquisto beni ex art.17', 'TD28' => 'TD28 - San Marino con IVA', 'TD29' => 'TD29 - Omessa/irregolare fatturazione']" />
                    <label>Data *<input wire:model.live="date" type="date" @disabled($this->readOnly)></label>
                    <label>Scadenza<input wire:model="due_date" type="date" @disabled($this->readOnly)></label>
                    <label>Numero fattura collegata<input wire:model="related_invoice_number" @disabled($this->readOnly)></label>
                    <label>Data fattura collegata<input wire:model="related_invoice_date" type="date" @disabled($this->readOnly)></label>
                </x-documents.invoice-form.data-fields>
            </x-documents.invoice-form.data-section>
            <x-documents.invoice-form.lines title="Righe autofattura" :read-only="$this->readOnly" variant="editor" class="mt-6">
                @foreach($lines as $index => $line)<x-documents.invoice-form.line :line="$line" :index="$index" :lines-count="count($lines)" :read-only="$this->readOnly" :line-total="$this->lineTotal($line)" :has-discount="false" :vat-disabled="false" variant="editor" />@endforeach
            </x-documents.invoice-form.lines>
        </article>
        <aside class="space-y-4">
            <x-documents.invoice-form.totals variant="editor" :net-total="$this->netTotal" :vat-total="$this->vatTotal" :gross-total="$this->grossTotal" note="L'autofattura viene registrata come saldata alla data di emissione." />
            <details @if($openNotes) open @endif class="rounded-xl border border-border bg-white"><summary class="flex cursor-pointer items-center justify-between px-5 py-4 text-sm font-semibold text-content marker:hidden">Note <x-icon name="o-chevron-down" class="size-4 text-content-muted" /></summary><div class="border-t border-border px-5 pb-5 pt-4"><label class="block text-sm font-medium text-content">Note<textarea wire:model="notes" @disabled($this->readOnly) rows="5" class="mt-1 w-full rounded-lg border border-border-strong bg-white px-3 py-2 text-sm text-content focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"></textarea></label></div></details>
            @if($invoice)<details class="rounded-xl border border-border bg-white"><summary class="flex cursor-pointer items-center justify-between px-5 py-4 text-sm font-semibold text-content marker:hidden">Storico <x-icon name="o-chevron-down" class="size-4 text-content-muted" /></summary><div class="space-y-3 border-t border-border px-5 pb-5 pt-4">@forelse($invoice->events as $event)<div class="border-l-2 border-primary pl-3"><p class="text-sm font-medium text-content">{{ $event->title }}</p><p class="mt-0.5 text-xs text-content-muted">{{ $event->occurred_at?->format('d/m/Y H:i') }} {{ $event->message }}</p></div>@empty<p class="text-sm text-content-muted">Nessun evento registrato.</p>@endforelse</div></details>@endif
        </aside>
        <x-documents.invoice-form.action-bar variant="editor" cancel-route="self-invoices.index" :submit-label="$invoice ? 'Aggiorna autofattura' : 'Crea autofattura'" :read-only="$this->readOnly" />
    </form>
</section>
