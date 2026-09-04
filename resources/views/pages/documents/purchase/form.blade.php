<?php

use App\Actions\SavePurchaseInvoice;
use App\Enums\VatRate;
use App\Models\Contact;
use App\Models\PurchaseInvoice;
use App\Services\PostHogTelemetryService;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component {
    public PurchaseInvoice $invoice;

    public int|string $contact_id = '';

    /** @var array<int, array{id: int, name: string, subtitle: string|null}> */
    public array $contactOptions = [];

    public string $number = '';

    public string $date = '';

    public string $due_date = '';

    public array $lines = [];

    public function mount(PurchaseInvoice $purchaseInvoice): void
    {
        $this->invoice = $purchaseInvoice->load('lines');
        $this->contactOptions = Contact::query()->orderBy('name')->get(['id', 'name', 'vat_number'])
            ->map(fn (Contact $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'subtitle' => $c->vat_number ? 'P.IVA '.$c->vat_number : null,
            ])->toArray();
        foreach (['contact_id', 'number'] as $field) {
            $this->{$field} = (string) $this->invoice->{$field};
        }
        $this->date = $this->invoice->date->toDateString();
        $this->due_date = $this->invoice->due_date?->toDateString() ?? '';
        $this->lines = $this->invoice->lines->map(fn ($line) => $this->lineState(['key' => (string) $line->id, 'description' => $line->description, 'quantity' => $line->quantity, 'unit_of_measure' => $line->unit_of_measure, 'unit_price' => $line->unit_price / 100, 'vat_rate' => $line->vat_rate->value]))->all();
    }

    public function addLine(): void
    {
        $this->lines[] = $this->lineState(['key' => (string) str()->uuid(), 'description' => '', 'quantity' => '1', 'unit_of_measure' => '', 'unit_price' => '0.00', 'vat_rate' => 'R22']);
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

    public function save(SavePurchaseInvoice $save): mixed
    {
        if ($this->readOnly) {
            $this->addError('invoice', 'Questa fattura non è più modificabile.');

            return null;
        }
        $saved = $save->update($this->invoice, $this->validate(['contact_id' => 'required|exists:contacts,id', 'number' => 'required|string', 'date' => 'required|date', 'due_date' => 'nullable|date', 'lines' => 'required|array|min:1', 'lines.*.description' => 'required|string', 'lines.*.quantity' => 'required|numeric|min:0.01', 'lines.*.unit_of_measure' => 'nullable|string', 'lines.*.unit_price' => 'required|numeric|min:0', 'lines.*.vat_rate' => 'required|string']));
        app(PostHogTelemetryService::class)->capture('purchase_invoice_updated', app(PostHogTelemetryService::class)->documentProperties($saved), auth()->user());

        return $this->redirectRoute('purchase-invoices.index', navigate: true);
    }

    public function getReadOnlyProperty(): bool
    {
        return ! $this->invoice->isSdiEditable() || $this->invoice->date->year < now()->year;
    }

    public function getNetTotalProperty(): float
    {
        return array_sum(array_map($this->lineTotal(...), $this->lines));
    }

    public function getVatTotalProperty(): float
    {
        return array_sum(array_map(fn ($l) => (float) $l['quantity'] * (float) $l['unit_price'] * (VatRate::tryFrom($l['vat_rate'])?->percent() ?? 0) / 100, $this->lines));
    }

    private function lineTotal(array $line): float
    {
        return max(0, (float) ($line['quantity'] ?: 0)) * max(0, (float) ($line['unit_price'] ?: 0));
    }

    private function lineState(array $line): array
    {
        return [...$line, 'quantity' => (string) $line['quantity'], 'unit_of_measure' => $line['unit_of_measure'] ?? '', 'unit_price' => number_format((float) $line['unit_price'], 2, '.', ''), 'details_enabled' => $line['quantity'] != 1 || ($line['unit_of_measure'] ?? '') !== ''];
    }
};
?>
<x-slot:header><div><p class="text-xs font-medium text-content-muted">Acquisti</p><h1 class="text-lg font-semibold text-content">Modifica fattura di acquisto</h1></div></x-slot:header>
<section class="mx-auto max-w-7xl space-y-6 pb-24">
    @if($this->readOnly)<div class="rounded-md border border-warning/20 bg-warning-bg p-4 text-warning">Questa fattura non è più modificabile.</div>@endif
    @error('invoice')<div class="rounded-md border border-danger/20 bg-danger-bg p-4 text-danger">{{ $message }}</div>@enderror
    <form wire:submit="save" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <article class="rounded-xl border border-border bg-white p-5 sm:p-6">
            <x-documents.invoice-form.data-section variant="editor">
                <div class="flex items-start justify-between gap-4"><div><h2 class="text-base font-semibold text-content">Dati fattura di acquisto</h2><p class="mt-1 text-sm text-content-muted">Fornitore, numero e condizioni del documento.</p></div><x-badge :value="$invoice->status?->label() ?? 'Bozza'" variant="neutral" /></div>
                <x-documents.invoice-form.data-fields variant="editor" class="mt-5">
                    <x-select label="Fornitore *" wire:model="contact_id" :disabled="$this->readOnly" :options="$contactOptions" searchable searchPlaceholder="Cerca per nome o P.IVA" placeholder="Seleziona fornitore..." />
                    <label>Numero *<input wire:model="number" @disabled($this->readOnly)></label>
                    <label>Data *<input wire:model="date" type="date" @disabled($this->readOnly)></label>
                    <label>Scadenza<input wire:model="due_date" type="date" @disabled($this->readOnly)></label>
                </x-documents.invoice-form.data-fields>
                <p class="mt-4 text-xs text-content-muted">La sequenza d'importazione non è modificabile.</p>
            </x-documents.invoice-form.data-section>
            <x-documents.invoice-form.lines title="Righe fattura" :read-only="$this->readOnly" variant="editor" class="mt-6">
                @foreach($lines as $index => $line)<x-documents.invoice-form.line :line="$line" :index="$index" :lines-count="count($lines)" :read-only="$this->readOnly" :line-total="$this->lineTotal($line)" :has-discount="false" :vat-disabled="false" variant="editor" />@endforeach
            </x-documents.invoice-form.lines>
        </article>
        <aside class="space-y-4"><x-documents.invoice-form.totals variant="editor" :net-total="$this->netTotal" :vat-total="$this->vatTotal" /></aside>
        <x-documents.invoice-form.action-bar variant="editor" cancel-route="purchase-invoices.index" submit-label="Aggiorna fattura" :read-only="$this->readOnly" />
    </form>
</section>
