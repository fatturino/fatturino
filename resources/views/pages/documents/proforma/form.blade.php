<?php

use App\Actions\SaveProformaInvoice;
use App\Enums\VatRate;
use App\Models\Contact;
use App\Models\ProformaInvoice;
use App\Services\DocumentEventRecorder;
use App\Services\DocumentSequenceResolver;
use App\Settings\CompanySettings;
use App\Settings\InvoiceSettings;
use Livewire\Attributes\Layout;
use Livewire\Component;

use App\Enums\PaymentMethod;
use App\Enums\PaymentTerms;

new #[Layout('layouts::app')] class extends Component {
    public ?ProformaInvoice $invoice = null;

    public int|string $contact_id = '';

    /** @var array<int, array{id: int, name: string, subtitle: string|null}> */
    public array $contactOptions = [];

    public ?string $numberPreview = null;

    public string $date = '';

    public string $due_date = '';

    public string $notes = '';

    public string $payment_method = '';

    public string $payment_terms = '';

    public string $bank_name = '';

    public string $bank_iban = '';

    public bool $withholding_tax_enabled = false;

    public string $withholding_tax_percent = '20.00';

    public bool $fund_enabled = false;

    public string $fund_percent = '4.00';

    public string $fund_vat_rate = '';

    public bool $stamp_duty_applied = false;

    public bool $stamp_duty_charged_to_customer = true;

    public array $lines = [];

    public string $tab = 'data';

    public function mount(?ProformaInvoice $proformaInvoice = null): void
    {
        $proformaInvoice = $proformaInvoice?->exists ? $proformaInvoice : null;
        $this->invoice = $proformaInvoice?->load(['lines', 'events' => fn ($query) => $query->latest('occurred_at')]);
        $settings = app(InvoiceSettings::class);
        $this->date = now()->toDateString();
        $this->contactOptions = Contact::query()->orderBy('name')->get(['id', 'name', 'vat_number'])
            ->map(fn (Contact $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'subtitle' => $c->vat_number ? 'P.IVA '.$c->vat_number : null,
            ])->toArray();
        foreach (['notes' => 'default_notes', 'payment_method' => 'default_payment_method', 'payment_terms' => 'default_payment_terms', 'bank_name' => 'default_bank_name', 'bank_iban' => 'default_bank_iban', 'withholding_tax_percent' => 'withholding_tax_percent', 'fund_percent' => 'fund_percent'] as $field => $setting) {
            $this->{$field} = (string) ($settings->{$setting} ?? '');
        }
        $this->withholding_tax_enabled = (bool) $settings->withholding_tax_enabled;
        $this->fund_enabled = (bool) $settings->fund_enabled;
        $this->fund_vat_rate = (string) ($settings->fund_vat_rate?->value ?? '');
        $this->stamp_duty_applied = (bool) $settings->auto_stamp_duty;
        if ($proformaInvoice) {
            foreach (['contact_id', 'notes', 'payment_method', 'payment_terms', 'bank_name', 'bank_iban', 'withholding_tax_percent', 'fund_percent', 'fund_vat_rate'] as $field) {
                $value = $proformaInvoice->{$field} ?? '';
                $this->{$field} = (string) ($value instanceof BackedEnum ? $value->value : $value);
            }
            foreach (['withholding_tax_enabled', 'fund_enabled', 'stamp_duty_applied', 'stamp_duty_charged_to_customer'] as $field) {
                $this->{$field} = (bool) $proformaInvoice->{$field};
            }
            $this->date = $proformaInvoice->date->toDateString();
            $this->due_date = $proformaInvoice->due_date?->toDateString() ?? '';
            $this->lines = $proformaInvoice->lines->map(fn ($line) => $this->lineState(['key' => (string) $line->id, 'description' => $line->description, 'quantity' => $line->quantity, 'unit_of_measure' => $line->unit_of_measure, 'unit_price' => $line->unit_price / 100, 'discount_percent' => $line->discount_percent, 'vat_rate' => $line->vat_rate->value]))->all();
        }
        if ($this->isRf19()) {
            $this->withholding_tax_enabled = false;
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

    public function updatedLinesDiscountEnabled(bool $enabled, string $key): void
    {
        if (! $enabled) {
            $this->lines[(int) explode('.', $key)[0]]['discount_percent'] = '';
        }
    }

    public function updatedLines(): void
    {
        if ($this->isRf19()) {
            $this->stamp_duty_applied = $this->netTotal > 77.47;
        }
    }

    public function updatedDate(): void
    {
        $this->refreshNumberPreview();
    }

    public function save(SaveProformaInvoice $saveProformaInvoice): mixed
    {
        if ($this->readOnly) {
            $this->addError('invoice', 'Questa proforma non è modificabile.');

            return null;
        }
        $payload = $this->validate($this->rules());
        $invoice = $this->invoice ? $saveProformaInvoice->update($this->invoice, $payload) : $saveProformaInvoice->create($payload);
        if (! $this->invoice) {
            app(DocumentEventRecorder::class)->created($invoice);
        }
        session()->flash('success', $this->invoice ? 'Proforma aggiornata.' : 'Proforma creata.');

        return $this->redirectRoute('proforma.index', navigate: true);
    }

    public function getNetTotalProperty(): float
    {
        return round(array_sum(array_map($this->lineTotal(...), $this->lines)), 2);
    }

    public function getFundAmountProperty(): float
    {
        return $this->fund_enabled ? round($this->netTotal * ((float) $this->fund_percent / 100), 2) : 0;
    }

    public function getVatTotalProperty(): float
    {
        return round(array_sum(array_map(fn ($line) => $this->lineTotal($line) * $this->vatPercent($line['vat_rate'] ?? '') / 100, $this->lines)) + $this->fundAmount * $this->vatPercent($this->fund_vat_rate) / 100, 2);
    }

    public function getStampDutyAmountProperty(): float
    {
        return $this->stamp_duty_applied ? 2.00 : 0.00;
    }

    public function getGrossTotalProperty(): float
    {
        return $this->netTotal + $this->fundAmount + $this->vatTotal;
    }

    public function getWithholdingAmountProperty(): float
    {
        return $this->withholding_tax_enabled ? round($this->netTotal * ((float) $this->withholding_tax_percent / 100), 2) : 0;
    }

    public function getNetDueProperty(): float
    {
        return max(0, $this->grossTotal + ($this->stamp_duty_charged_to_customer ? $this->stampDutyAmount : 0) - $this->withholdingAmount);
    }

    private function refreshNumberPreview(): void
    {
        $this->numberPreview = $this->invoice?->number ?? app(DocumentSequenceResolver::class)->resolve('proforma')->getFormattedNumber((int) substr($this->date, 0, 4));
    }

    public function getReadOnlyProperty(): bool
    {
        return $this->invoice && (! in_array($this->invoice->status->value, ['draft', 'sent'], true) || $this->invoice->date->year < now()->year);
    }

    private function rules(): array
    {
        return ['contact_id' => 'required|exists:contacts,id', 'date' => 'required|date', 'due_date' => 'nullable|date', 'notes' => 'nullable|string', 'withholding_tax_enabled' => 'boolean', 'withholding_tax_percent' => 'nullable|numeric|min:0|max:100', 'fund_enabled' => 'boolean', 'fund_percent' => 'nullable|numeric|min:0|max:100', 'fund_vat_rate' => 'nullable|string', 'stamp_duty_applied' => 'boolean', 'stamp_duty_charged_to_customer' => 'boolean', 'payment_method' => 'nullable|string', 'payment_terms' => 'nullable|string', 'bank_name' => 'nullable|string', 'bank_iban' => 'nullable|string', 'lines' => 'required|array|min:1', 'lines.*.description' => 'required|string', 'lines.*.quantity' => 'required|numeric|min:0.01', 'lines.*.unit_of_measure' => 'nullable|string', 'lines.*.unit_price' => 'required|numeric|min:0', 'lines.*.discount_percent' => 'nullable|numeric|min:0|max:100', 'lines.*.vat_rate' => 'required|string'];
    }

    private function emptyLine(): array
    {
        return $this->lineState(['key' => (string) str()->uuid(), 'description' => '', 'quantity' => 1, 'unit_of_measure' => '', 'unit_price' => '0.00', 'discount_percent' => '', 'vat_rate' => $this->isRf19() ? 'N2.2' : 'R22']);
    }

    private function lineState(array $line): array
    {
        return [...$line, 'quantity' => (string) $line['quantity'], 'unit_of_measure' => $line['unit_of_measure'] ?? '', 'unit_price' => number_format((float) $line['unit_price'], 2, '.', ''), 'discount_percent' => $line['discount_percent'] ?? '', 'details_enabled' => $line['quantity'] != 1 || ($line['unit_of_measure'] ?? '') !== '' || ($line['discount_percent'] ?? null) !== null, 'discount_enabled' => ($line['discount_percent'] ?? null) !== null];
    }

    public function isRf19(): bool
    {
        return app(CompanySettings::class)->company_fiscal_regime === 'RF19';
    }

    private function lineTotal(array $line): float
    {
        return max(0, (float) ($line['quantity'] ?: 0)) * max(0, (float) ($line['unit_price'] ?: 0)) * (1 - max(0, (float) ($line['discount_percent'] ?: 0)) / 100);
    }

    private function vatPercent(string $value): float
    {
        return VatRate::tryFrom($value)?->percent() ?? 0;
    }
};
?>

<x-slot:header>
    <div>
        <p class="text-xs font-medium text-content-muted">Vendite</p>
        <h1 class="text-lg font-semibold text-content">{{ $invoice ? 'Modifica proforma' : 'Nuova proforma' }}</h1>
    </div>
</x-slot:header>

@php
    $editorStatus = $invoice?->status?->label() ?? 'Bozza';
    $openPayment = filled($payment_method) || filled($payment_terms) || filled($bank_name) || filled($bank_iban);
    $openNotes = filled($notes);
@endphp

<section class="mx-auto max-w-7xl space-y-6 pb-24">
    @if(session('success'))<div class="rounded-md border border-success/20 bg-success-bg p-4 text-sm text-success">{{ session('success') }}</div>@endif
    @if($this->readOnly)<div class="rounded-md border border-warning/20 bg-warning-bg p-4 text-sm text-warning">Questa proforma non è più modificabile.</div>@endif
    @error('invoice')<div class="rounded-md border border-danger/20 bg-danger-bg p-4 text-sm text-danger">{{ $message }}</div>@enderror

    <form wire:submit="save" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <article class="rounded-xl border border-border bg-white p-5 sm:p-6">
            <x-documents.invoice-form.data-section variant="editor">
                <div class="flex items-start justify-between gap-4">
                    <div><h2 class="text-base font-semibold text-content">Dati proforma</h2><p class="mt-1 text-sm text-content-muted">Cliente, numero e condizioni del documento.</p></div>
                    <span class="inline-flex items-center gap-2 text-xs font-medium text-content-muted"><span class="size-1.5 rounded-full bg-primary"></span>{{ $editorStatus }}</span>
                </div>
                <x-documents.invoice-form.data-fields variant="editor" class="mt-5">
                    <x-select label="Cliente *" wire:model="contact_id" :disabled="$this->readOnly" :options="$contactOptions" searchable searchPlaceholder="Cerca per nome o P.IVA" placeholder="Seleziona cliente..." />
                    <label>Numero<div class="mt-1 flex h-11 items-center rounded-lg border border-border-strong bg-surface-muted px-3 text-sm text-content-muted">{{ $numberPreview ?? 'Configura una sequenza proforma' }}</div></label>
                    <label>Data *<input wire:model.live="date" type="date" @disabled($this->readOnly)></label>
                    <label>Scadenza<input wire:model="due_date" type="date" @disabled($this->readOnly)></label>
                </x-documents.invoice-form.data-fields>
            </x-documents.invoice-form.data-section>

            <x-documents.invoice-form.lines title="Righe proforma" :read-only="$this->readOnly" variant="editor" class="mt-6">
                @foreach($lines as $index => $line)
                    <x-documents.invoice-form.line :line="$line" :index="$index" :lines-count="count($lines)" :read-only="$this->readOnly" :line-total="$this->lineTotal($line)" :has-discount="true" :vat-disabled="$this->isRf19()" variant="editor" />
                @endforeach
            </x-documents.invoice-form.lines>
        </article>

        <aside class="space-y-4">
            <x-documents.invoice-form.totals variant="editor" :net-total="$this->netTotal" :vat-total="$this->vatTotal" :fund-amount="$fund_enabled ? $this->fundAmount : 0" :fund-percent="$fund_enabled ? $fund_percent : null" :stamp-duty-amount="$stamp_duty_applied ? $this->stampDutyAmount : 0" :stamp-duty-label="'Bollo '.($stamp_duty_charged_to_customer ? 'a carico cliente' : 'a carico cedente')" :withholding-amount="$this->withholdingAmount" :withholding-percent="$withholding_tax_enabled ? $withholding_tax_percent : null" :net-due="$this->netDue" />

            <details @if($openPayment) open @endif class="rounded-xl border border-border bg-white">
                <summary class="flex cursor-pointer items-center justify-between px-5 py-4 text-sm font-semibold text-content marker:hidden">Pagamento <x-icon name="o-chevron-down" class="size-4 text-content-muted" /></summary>
                <div class="grid gap-4 border-t border-border px-5 pb-5 pt-4 sm:grid-cols-2">
                    <label class="text-sm font-medium text-content">Metodo pagamento<x-select wire:model="payment_method" :disabled="$this->readOnly" :options="PaymentMethod::options()" placeholder="Seleziona..." /></label>
                    <label class="text-sm font-medium text-content">Termini pagamento<x-select wire:model="payment_terms" :disabled="$this->readOnly" :options="PaymentTerms::options()" placeholder="Seleziona..." /></label>
                    <label class="text-sm font-medium text-content sm:col-span-2">Banca<input wire:model="bank_name" @disabled($this->readOnly) class="mt-1 h-11 w-full rounded-lg border border-border-strong bg-white px-3 text-sm text-content focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"></label>
                    <label class="text-sm font-medium text-content sm:col-span-2">IBAN<input wire:model="bank_iban" @disabled($this->readOnly) class="mt-1 h-11 w-full rounded-lg border border-border-strong bg-white px-3 text-sm text-content focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"></label>
                </div>
            </details>

            <details @if($openNotes) open @endif class="rounded-xl border border-border bg-white">
                <summary class="flex cursor-pointer items-center justify-between px-5 py-4 text-sm font-semibold text-content marker:hidden">Note <x-icon name="o-chevron-down" class="size-4 text-content-muted" /></summary>
                <div class="border-t border-border px-5 pb-5 pt-4"><label class="block text-sm font-medium text-content">Note<textarea wire:model="notes" @disabled($this->readOnly) rows="5" class="mt-1 w-full rounded-lg border border-border-strong bg-white px-3 py-2 text-sm text-content focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"></textarea></label></div>
            </details>

            <x-documents.invoice-form.fiscal-options variant="editor" :read-only="$this->readOnly" :is-rf19="$this->isRf19()" :withholding="true" :fund="true" :stamp-duty="true" :stamp-duty-charged-to-customer="true" :withholding-enabled="$withholding_tax_enabled" :fund-enabled="$fund_enabled" :stamp-duty-applied="$stamp_duty_applied" />

            @if($invoice)
                <details class="rounded-xl border border-border bg-white"><summary class="flex cursor-pointer items-center justify-between px-5 py-4 text-sm font-semibold text-content marker:hidden">Storico <x-icon name="o-chevron-down" class="size-4 text-content-muted" /></summary><div class="space-y-3 border-t border-border px-5 pb-5 pt-4">@forelse($invoice->events as $event)<div class="border-l-2 border-primary pl-3"><p class="text-sm font-medium text-content">{{ $event->title }}</p><p class="mt-0.5 text-xs text-content-muted">{{ $event->occurred_at?->format('d/m/Y H:i') }} {{ $event->message }}</p></div>@empty<p class="text-sm text-content-muted">Nessun evento registrato.</p>@endforelse</div></details>
            @endif
        </aside>

        <x-documents.invoice-form.action-bar variant="editor" cancel-route="proforma.index" :submit-label="$invoice ? 'Aggiorna proforma' : 'Crea proforma'" :read-only="$this->readOnly" />
    </form>
</section>
