<?php

use App\Actions\SaveSalesInvoice;
use App\Enums\PaymentMethod;
use App\Enums\PaymentTerms;
use App\Enums\SalesDocumentType;
use App\Enums\VatRate;
use App\Models\Contact;
use App\Models\SalesInvoice;
use App\Services\DocumentSequenceResolver;
use App\Services\DocumentEventRecorder;
use App\Services\PostHogTelemetryService;
use App\Settings\CompanySettings;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component {
    public ?SalesInvoice $invoice = null;

    public int|string $contact_id = '';

    /** @var array<int, array{id: int, name: string}> */
    public array $contactOptions = [];

    public ?string $numberPreview = null;

    public string $date = '';

    public string $due_date = '';

    public string $document_type = 'TD01';

    public string $notes = '';

    public string $payment_method = '';

    public string $payment_terms = '';

    public string $bank_name = '';

    public string $bank_iban = '';

    public bool $withholding_tax_enabled = false;

    public string $withholding_tax_percent = '20.00';

    public bool $fund_enabled = false;

    public string $fund_type = '';

    public string $fund_percent = '4.00';

    public string $fund_vat_rate = '';

    public bool $fund_has_deduction = false;

    public bool $stamp_duty_applied = false;

    public bool $stamp_duty_charged_to_customer = true;

    public bool $split_payment = false;

    public string $vat_payability = 'I';

    public array $lines = [];

    public string $tab = 'data';

    public function mount(?SalesInvoice $invoice = null): void
    {
        $invoice = $invoice?->exists ? $invoice : null;
        $this->invoice = $invoice?->load(['lines', 'events' => fn ($query) => $query->latest('occurred_at')]);
        $settings = app(\App\Settings\InvoiceSettings::class);
        $this->date = now()->toDateString();
        $this->contactOptions = Contact::query()->orderBy('name')->get(['id', 'name'])->toArray();
        $this->notes = $settings->default_notes ?? '';
        $this->payment_method = (string) ($settings->default_payment_method ?? '');
        $this->payment_terms = (string) ($settings->default_payment_terms ?? '');
        $this->bank_name = (string) ($settings->default_bank_name ?? '');
        $this->bank_iban = (string) ($settings->default_bank_iban ?? '');
        $this->withholding_tax_enabled = (bool) $settings->withholding_tax_enabled;
        $this->withholding_tax_percent = (string) $settings->withholding_tax_percent;
        $this->fund_enabled = (bool) $settings->fund_enabled;
        $this->fund_type = (string) ($settings->fund_type ?? '');
        $this->fund_percent = (string) $settings->fund_percent;
        $this->fund_vat_rate = (string) ($settings->fund_vat_rate?->value ?? '');
        $this->fund_has_deduction = (bool) $settings->fund_has_deduction;
        $this->stamp_duty_applied = (bool) $settings->auto_stamp_duty;
        $this->vat_payability = (string) ($settings->default_vat_payability ?? 'I');
        $this->split_payment = (bool) $settings->default_split_payment;
        if ($this->isRf19()) {
            $this->withholding_tax_enabled = false;
            $this->split_payment = false;
            $this->vat_payability = 'I';
        }
        if ($invoice) {
            foreach (['contact_id', 'document_type', 'notes', 'payment_method', 'payment_terms', 'bank_name', 'bank_iban', 'withholding_tax_percent', 'fund_type', 'fund_percent', 'fund_vat_rate', 'vat_payability'] as $field) {
                $this->{$field} = (string) ($invoice->{$field} ?? '');
            }
            foreach (['withholding_tax_enabled', 'fund_enabled', 'fund_has_deduction', 'stamp_duty_applied', 'stamp_duty_charged_to_customer', 'split_payment'] as $field) {
                $this->{$field} = (bool) $invoice->{$field};
            }
            $this->date = $invoice->date->toDateString();
            $this->due_date = $invoice->due_date?->toDateString() ?? '';
            $this->lines = $invoice->lines->map(fn ($line) => ['key' => (string) $line->id, 'description' => $line->description, 'quantity' => (string) $line->quantity, 'unit_of_measure' => $line->unit_of_measure ?? '', 'unit_price' => number_format($line->unit_price / 100, 2, '.', ''), 'discount_percent' => $line->discount_percent ?? '', 'vat_rate' => $line->vat_rate->value, 'details_enabled' => $line->quantity != 1 || $line->unit_of_measure !== '' || $line->discount_percent !== null, 'discount_enabled' => $line->discount_percent !== null])->all();
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

    public function updatedSplitPayment(): void
    {
        if ($this->split_payment) {
            $this->vat_payability = 'S';
        }
    }

    public function updatedDate(): void
    {
        $this->refreshNumberPreview();
    }

    public function updatedLines(): void
    {
        if ($this->isRf19()) {
            $this->stamp_duty_applied = $this->netTotal > 77.47;
        }
    }

    public function save(SaveSalesInvoice $saveSalesInvoice): mixed
    {
        if ($this->readOnly) {
            $this->addError('invoice', 'Questa fattura non è modificabile.');

            return null;
        }
        $payload = $this->validate($this->rules());
        $invoice = $this->invoice ? $saveSalesInvoice->update($this->invoice, $payload) : $saveSalesInvoice->create($payload);
        if (! $this->invoice) {
            app(DocumentEventRecorder::class)->created($invoice);
        }
        app(PostHogTelemetryService::class)->capture($this->invoice ? 'sales_invoice_updated' : 'sales_invoice_created', app(PostHogTelemetryService::class)->documentProperties($invoice), auth()->user());
        session()->flash('success', $this->invoice ? 'Fattura aggiornata.' : 'Fattura creata.');

        return $this->redirectRoute('sell-invoices.index', navigate: true);
    }

    public function getNetTotalProperty(): float
    {
        return round(array_sum(array_map(fn ($line) => max(0, (float) ($line['quantity'] ?: 0)) * max(0, (float) ($line['unit_price'] ?: 0)) * (1 - max(0, (float) ($line['discount_percent'] ?: 0)) / 100), $this->lines)), 2);
    }

    public function getVatTotalProperty(): float
    {
        return round(array_sum(array_map(fn ($line) => $this->lineTotal($line) * $this->vatPercent($line['vat_rate'] ?? '') / 100, $this->lines)), 2);
    }

    public function getStampDutyAmountProperty(): float
    {
        return $this->stamp_duty_applied ? 2.00 : 0.00;
    }

    public function getGrossTotalProperty(): float
    {
        return $this->netTotal + $this->vatTotal;
    }

    public function getNetDueProperty(): float
    {
        return max(0, $this->grossTotal + ($this->stamp_duty_charged_to_customer ? $this->stampDutyAmount : 0) - ($this->withholding_tax_enabled ? $this->netTotal * ((float) $this->withholding_tax_percent / 100) : 0) - ($this->split_payment ? $this->vatTotal : 0));
    }

    private function refreshNumberPreview(): void
    {
        $this->numberPreview = $this->invoice?->number
            ?? app(DocumentSequenceResolver::class)->resolve('sales')->getFormattedNumber((int) substr($this->date, 0, 4));
    }

    public function getReadOnlyProperty(): bool
    {
        return $this->invoice && (! $this->invoice->isSdiEditable() || $this->invoice->date->year < now()->year);
    }

    private function rules(): array
    {
        return ['contact_id' => 'required|exists:contacts,id', 'date' => 'required|date', 'due_date' => 'nullable|date', 'document_type' => 'required|string', 'notes' => 'nullable|string', 'withholding_tax_enabled' => 'boolean', 'withholding_tax_percent' => 'nullable|numeric|min:0|max:100', 'fund_enabled' => 'boolean', 'fund_type' => 'nullable|string', 'fund_percent' => 'nullable|numeric|min:0|max:100', 'fund_vat_rate' => 'nullable|string', 'fund_has_deduction' => 'boolean', 'stamp_duty_applied' => 'boolean', 'stamp_duty_charged_to_customer' => 'boolean', 'payment_method' => 'nullable|string', 'payment_terms' => 'nullable|string', 'bank_name' => 'nullable|string', 'bank_iban' => 'nullable|string', 'vat_payability' => 'required|string', 'split_payment' => 'boolean', 'lines' => 'required|array|min:1', 'lines.*.description' => 'required|string', 'lines.*.quantity' => 'required|numeric|min:0.01', 'lines.*.unit_of_measure' => 'nullable|string', 'lines.*.unit_price' => 'required|numeric|min:0', 'lines.*.discount_percent' => 'nullable|numeric|min:0|max:100', 'lines.*.vat_rate' => 'required|string'];
    }

    private function emptyLine(): array
    {
        return ['key' => (string) str()->uuid(), 'description' => '', 'quantity' => '1', 'unit_of_measure' => '', 'unit_price' => '0.00', 'discount_percent' => '', 'vat_rate' => $this->isRf19() ? 'N2.2' : 'R22', 'details_enabled' => false, 'discount_enabled' => false];
    }

    public function isRf19(): bool
    {
        return app(CompanySettings::class)->company_fiscal_regime === 'RF19';
    }

    private function lineTotal(array $line): float
    {
        return (float) ($line['quantity'] ?: 0) * (float) ($line['unit_price'] ?: 0) * (1 - (float) ($line['discount_percent'] ?: 0) / 100);
    }

    private function vatPercent(string $value): float
    {
        return VatRate::tryFrom($value)?->percent() ?? 0;
    }
};
?>

<x-slot:header>
    <div>
        <p class="text-xs font-bold uppercase tracking-[.12em] text-content-muted">Vendite</p>
        <h1 class="text-lg font-bold text-content">{{ $invoice ? 'Modifica fattura' : 'Nuova fattura' }}</h1>
    </div>
</x-slot:header>

<section class="mx-auto max-w-7xl space-y-6 pb-24">
    @if(session('success'))
        <div class="rounded-md border border-success/20 bg-success-bg p-4 text-sm text-success">{{ session('success') }}</div>
    @endif
    @if($this->readOnly)
        <div class="rounded-md border border-warning/20 bg-warning-bg p-4 text-sm text-warning">Questa fattura non è più modificabile.</div>
    @endif
    @error('invoice')<div class="rounded-md border border-danger/20 bg-danger-bg p-4 text-sm text-danger">{{ $message }}</div>@enderror

    <form wire:submit="save" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="space-y-6">
            <x-documents.invoice-form.data-section>
                <nav class="mb-5 flex gap-2 border-b border-border-light pb-4" aria-label="Sezioni fattura">
                    @foreach(['data' => 'Dati', 'payment' => 'Pagamento', 'notes' => 'Note'] as $key => $label)
                        <button type="button" wire:click="$set('tab', '{{ $key }}')" class="rounded-md px-3 py-2 text-sm font-semibold {{ $tab === $key ? 'bg-primary text-white' : 'text-content-muted' }}">{{ $label }}</button>
                    @endforeach
                    @if($invoice)<button type="button" wire:click="$set('tab', 'history')" class="rounded-md px-3 py-2 text-sm font-semibold {{ $tab === 'history' ? 'bg-primary text-white' : 'text-content-muted' }}">Storico</button>@endif
                </nav>

                @if($tab === 'data')
                    <x-documents.invoice-form.data-fields>
                        <label class="text-sm font-semibold">Cliente *
                            <select wire:model="contact_id" @disabled($this->readOnly) class="mt-1 h-11 w-full rounded-md border border-border bg-white px-3 text-sm">
                                <option value="">Seleziona cliente...</option>
                                @foreach($contactOptions as $contact)<option value="{{ $contact['id'] }}">{{ $contact['name'] }}</option>@endforeach
                            </select>
                            @error('contact_id')<span class="text-xs text-danger">{{ $message }}</span>@enderror
                        </label>
                        <div class="text-sm font-semibold">Numero
                            <div class="mt-1 h-11 rounded-md border border-border-light bg-surface-muted px-3 py-3 text-sm font-normal">{{ $numberPreview ?? 'Configura il sezionale predefinito' }}</div>
                        </div>
                        <label class="text-sm font-semibold">Data *<input wire:model.live="date" type="date" @disabled($this->readOnly) class="mt-1 h-11 w-full rounded-md border border-border px-3 text-sm">@error('date')<span class="text-xs text-danger">{{ $message }}</span>@enderror</label>
                        <label class="text-sm font-semibold">Scadenza<input wire:model="due_date" type="date" @disabled($this->readOnly) class="mt-1 h-11 w-full rounded-md border border-border px-3 text-sm"></label>
                        <label class="text-sm font-semibold">Tipo documento *
                            <select wire:model="document_type" @disabled($this->readOnly) class="mt-1 h-11 w-full rounded-md border border-border bg-white px-3 text-sm">@foreach(SalesDocumentType::options() as $type)<option value="{{ $type['id'] }}">{{ $type['name'] }}</option>@endforeach</select>
                        </label>
                    </x-documents.invoice-form.data-fields>
                @elseif($tab === 'payment')
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="text-sm font-semibold">Metodo pagamento<select wire:model="payment_method" @disabled($this->readOnly) class="mt-1 h-11 w-full rounded-md border border-border bg-white px-3 text-sm"><option value="">Seleziona...</option>@foreach(PaymentMethod::options() as $option)<option value="{{ $option['id'] }}">{{ $option['name'] }}</option>@endforeach</select></label>
                        <label class="text-sm font-semibold">Termini pagamento<select wire:model="payment_terms" @disabled($this->readOnly) class="mt-1 h-11 w-full rounded-md border border-border bg-white px-3 text-sm"><option value="">Seleziona...</option>@foreach(PaymentTerms::options() as $option)<option value="{{ $option['id'] }}">{{ $option['name'] }}</option>@endforeach</select></label>
                        <label class="text-sm font-semibold">Banca<input wire:model="bank_name" @disabled($this->readOnly) class="mt-1 h-11 w-full rounded-md border border-border px-3 text-sm"></label>
                        <label class="text-sm font-semibold">IBAN<input wire:model="bank_iban" @disabled($this->readOnly) class="mt-1 h-11 w-full rounded-md border border-border px-3 text-sm"></label>
                    </div>
                @elseif($tab === 'notes')
                    <label class="text-sm font-semibold">Note<textarea wire:model="notes" @disabled($this->readOnly) rows="5" class="mt-1 w-full rounded-md border border-border px-3 py-2 text-sm"></textarea></label>
                @else
                    <div class="space-y-3">@forelse($invoice->events as $event)<div class="border-l-2 border-primary pl-3"><p class="text-sm font-semibold">{{ $event->title }}</p><p class="text-xs text-content-muted">{{ $event->occurred_at?->format('d/m/Y H:i') }} {{ $event->message }}</p></div>@empty<p class="text-sm text-content-muted">Nessun evento registrato.</p>@endforelse</div>
                @endif
            </x-documents.invoice-form.data-section>

            <x-documents.invoice-form.lines title="Righe fattura" :read-only="$this->readOnly">
                @foreach($lines as $index => $line)
                    <x-documents.invoice-form.line :line="$line" :index="$index" :lines-count="count($lines)" :read-only="$this->readOnly" :line-total="$this->lineTotal($line)" :has-discount="true" :vat-disabled="$this->isRf19()" />
                @endforeach
            </x-documents.invoice-form.lines>
        </div>

        <aside class="space-y-4">
            <x-documents.invoice-form.totals :net-total="$this->netTotal" :vat-total="$this->vatTotal" :net-due="$this->netDue" :stamp-duty-amount="$stamp_duty_applied ? $this->stampDutyAmount : 0" :stamp-duty-label="'Bollo '.($stamp_duty_charged_to_customer ? 'a carico cliente' : 'a carico cedente')" />
            <x-documents.invoice-form.fiscal-options :read-only="$this->readOnly" :is-rf19="$this->isRf19()" :withholding="true" :fund="true" :stamp-duty="true" :split-payment="true" :stamp-duty-charged-to-customer="true" :withholding-enabled="$withholding_tax_enabled" :fund-enabled="$fund_enabled" :stamp-duty-applied="$stamp_duty_applied" :split-payment-enabled="$split_payment" />
        </aside>
        <x-documents.invoice-form.action-bar cancel-route="sell-invoices.index" :submit-label="$invoice ? 'Aggiorna fattura' : 'Crea fattura'" :read-only="$this->readOnly" />
    </form>
</section>
