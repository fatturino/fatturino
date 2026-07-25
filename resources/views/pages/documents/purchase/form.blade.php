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
    public string $number = '';
    public string $date = '';
    public string $due_date = '';
    public array $lines = [];
    public function mount(PurchaseInvoice $purchaseInvoice): void { $this->invoice = $purchaseInvoice->load('lines'); foreach (['contact_id','number'] as $field) $this->{$field} = (string) $this->invoice->{$field}; $this->date=$this->invoice->date->toDateString(); $this->due_date=$this->invoice->due_date?->toDateString()??''; $this->lines=$this->invoice->lines->map(fn($l)=>['key'=>(string)$l->id,'description'=>$l->description,'quantity'=>(string)$l->quantity,'unit_of_measure'=>$l->unit_of_measure??'','unit_price'=>number_format($l->unit_price/100,2,'.',''),'vat_rate'=>$l->vat_rate->value])->all(); }
    public function addLine(): void { $this->lines[]=['key'=>(string)str()->uuid(),'description'=>'','quantity'=>'1','unit_of_measure'=>'','unit_price'=>'0.00','vat_rate'=>'R22']; }
    public function removeLine(int $index): void { if(count($this->lines)>1) array_splice($this->lines,$index,1); }
    public function save(SavePurchaseInvoice $save): mixed { if($this->readOnly){$this->addError('invoice','Questa fattura non è più modificabile.');return null;} $saved=$save->update($this->invoice,$this->validate(['contact_id'=>'required|exists:contacts,id','number'=>'required|string','date'=>'required|date','due_date'=>'nullable|date','lines'=>'required|array|min:1','lines.*.description'=>'required|string','lines.*.quantity'=>'required|numeric|min:0.01','lines.*.unit_of_measure'=>'nullable|string','lines.*.unit_price'=>'required|numeric|min:0','lines.*.vat_rate'=>'required|string'])); app(PostHogTelemetryService::class)->capture('purchase_invoice_updated',app(PostHogTelemetryService::class)->documentProperties($saved),auth()->user()); return $this->redirectRoute('purchase-invoices.index',navigate:true); }
    public function getReadOnlyProperty(): bool { return ! $this->invoice->isSdiEditable() || $this->invoice->date->year < now()->year; }
    public function getNetTotalProperty(): float { return array_sum(array_map($this->lineTotal(...),$this->lines)); }
    public function getVatTotalProperty(): float { return array_sum(array_map(fn($l)=>(float)$l['quantity']*(float)$l['unit_price']*(VatRate::tryFrom($l['vat_rate'])?->percent()??0)/100,$this->lines)); }
    private function lineTotal(array $line): float { return max(0, (float) ($line['quantity'] ?: 0)) * max(0, (float) ($line['unit_price'] ?: 0)); }
}; ?>
<x-slot:header><div><p class="text-xs font-bold uppercase tracking-[.12em] text-content-muted">Acquisti</p><h1 class="text-lg font-bold text-content">Modifica fattura di acquisto</h1></div></x-slot:header>
<section class="mx-auto max-w-7xl space-y-6 pb-24">@if($this->readOnly)<div class="rounded-md border border-warning/20 bg-warning-bg p-4 text-warning">Questa fattura non è più modificabile.</div>@endif @error('invoice')<div class="rounded-md border border-danger/20 bg-danger-bg p-4 text-danger">{{ $message }}</div>@enderror<form wire:submit="save" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]"><div class="space-y-6"><article class="rounded-xl border border-border-light bg-white p-5"><div class="grid gap-4 sm:grid-cols-2"><label>Fornitore *<select wire:model="contact_id" @disabled($this->readOnly) class="mt-1 h-11 w-full rounded border p-2">@foreach(Contact::orderBy('name')->get(['id','name']) as $contact)<option value="{{ $contact->id }}">{{ $contact->name }}</option>@endforeach</select></label><label>Numero *<input wire:model="number" @disabled($this->readOnly) class="mt-1 h-11 w-full rounded border p-2"></label><label>Data *<input wire:model="date" type="date" @disabled($this->readOnly) class="mt-1 h-11 w-full rounded border p-2"></label><label>Scadenza<input wire:model="due_date" type="date" @disabled($this->readOnly) class="mt-1 h-11 w-full rounded border p-2"></label></div><p class="mt-4 text-xs text-content-muted">La sequenza d'importazione non è modificabile.</p></article><x-documents.invoice-form.lines title="Righe fattura" :read-only="$this->readOnly">
                @foreach($lines as $index => $line)
                    <x-documents.invoice-form.line :line="$line" :index="$index" :lines-count="count($lines)" :read-only="$this->readOnly" :line-total="$this->lineTotal($line)" :has-discount="false" :vat-disabled="false" />
                @endforeach
            </x-documents.invoice-form.lines></div><aside class="space-y-4">
            <x-documents.invoice-form.totals :net-total="$this->netTotal" :vat-total="$this->vatTotal" />
        </aside><x-documents.invoice-form.action-bar cancel-route="purchase-invoices.index" submit-label="Aggiorna fattura" :read-only="$this->readOnly" /></form></section>
