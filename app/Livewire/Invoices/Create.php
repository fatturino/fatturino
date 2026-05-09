<?php

namespace App\Livewire\Invoices;

use App\Enums\InvoiceStatus;
use App\Enums\VatRate;
use App\Livewire\Traits\CalculatesInvoiceTotals;
use App\Livewire\Traits\HandlesReverseCalculation;
use App\Livewire\Traits\ManagesInvoiceLines;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Sequence;
use App\Settings\InvoiceSettings;
use App\Traits\Toast;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Create extends Component
{
    use CalculatesInvoiceTotals, HandlesReverseCalculation, ManagesInvoiceLines, Toast;

    #[Validate('required')]
    public ?string $number = null;

    #[Validate('required|date')]
    public string $date = '';

    #[Validate('required|exists:contacts,id')]
    public ?int $contact_id = null;

    #[Validate('required|exists:sequences,id')]
    public ?int $sequence_id = null;

    // Withholding tax (Ritenuta d'acconto)
    public bool $withholding_tax_enabled = false;

    public string $withholding_tax_percent = '20.00';

    // Professional fund (Cassa Previdenziale)
    public bool $fund_enabled = false;

    public ?string $fund_type = null;

    public string $fund_percent = '4.00';

    public ?string $fund_vat_rate = null;

    public bool $fund_has_deduction = false;

    // Stamp duty (Marca da bollo)
    public bool $stamp_duty_applied = false;

    public bool $auto_stamp_duty = false;

    public string $stamp_duty_threshold = '77.47';

    // Payment details (DatiPagamento)
    public ?string $payment_method = null;

    public ?string $payment_terms = null;

    public ?string $bank_name = null;

    public ?string $bank_iban = null;

    // VAT payability and split payment
    public string $vat_payability = 'I';

    public bool $split_payment = false;

    // Notes / Causale (used as document description in XML)
    public ?string $notes = null;

    // Document type (TD01, TD02, TD03, TD06, TD24, TD25)
    public string $document_type = 'TD01';

    public function updatedSequenceId()
    {
        $sequence = Sequence::find($this->sequence_id);
        if ($sequence) {
            $this->number = $sequence->getFormattedNumber();
        }
    }

    // Autosave draft
    public ?string $draftSavedAt = null;

    public function mount(InvoiceSettings $settings)
    {
        // Prevent creating invoices when a past fiscal year is selected
        if (session('fiscal_year', now()->year) < now()->year) {
            $this->redirectRoute('sell-invoices.index', navigate: true);

            return;
        }

        $this->date = now()->format('Y-m-d');

        // Initialize withholding tax from global settings
        $this->withholding_tax_enabled = $settings->withholding_tax_enabled;
        $this->withholding_tax_percent = $settings->withholding_tax_percent;

        // Initialize fund from invoice settings
        $this->fund_enabled = $settings->fund_enabled;
        $this->fund_type = $settings->fund_type;
        $this->fund_percent = $settings->fund_percent;
        $this->fund_vat_rate = $settings->fund_vat_rate?->value;
        $this->fund_has_deduction = $settings->fund_has_deduction;

        // Initialize stamp duty from settings (auto-toggle based on total in updatedLines)
        $this->auto_stamp_duty = $settings->auto_stamp_duty;
        $this->stamp_duty_threshold = $settings->stamp_duty_threshold;

        // Initialize payment details from settings
        $this->payment_method = $settings->default_payment_method;
        $this->payment_terms = $settings->default_payment_terms;
        $this->bank_name = $settings->default_bank_name;
        $this->bank_iban = $settings->default_bank_iban;

        // Initialize VAT payability and split payment from settings
        $this->vat_payability = $settings->default_vat_payability ?? 'I';
        $this->split_payment = $settings->default_split_payment ?? false;

        // Initialize notes from settings
        $this->notes = $settings->default_notes;

        // Set default sequence (prefer system, fallback to first available)
        $defaultSequence = Sequence::where('type', 'electronic_invoice')
            ->orderByDesc('is_system')
            ->first();
        if ($defaultSequence) {
            $this->sequence_id = $defaultSequence->id;
            $this->updatedSequenceId();
        }

        // Restore draft if one exists
        $draft = Cache::get('invoice_draft_'.auth()->id());
        if ($draft) {
            foreach ($draft as $key => $value) {
                if (property_exists($this, $key)) {
                    $this->{$key} = $value;
                }
            }
            $this->draftSavedAt = null; // Will be set on next autosave
        }

        // Add initial line only if no lines restored from draft
        if (empty($this->lines)) {
            $this->addLine();
        }
    }

    public function save()
    {
        $this->validate();

        // Atomically reserve next number to prevent duplicates
        $sequence = Sequence::find($this->sequence_id);
        $year = Carbon::parse($this->date)->year;
        $reserved = $sequence->reserveNextNumber($year);

        $invoice = Invoice::create([
            'number' => $reserved['formatted_number'],
            'sequential_number' => $reserved['sequential_number'],
            'date' => $this->date,
            'contact_id' => $this->contact_id,
            'sequence_id' => $this->sequence_id,
            'fiscal_year' => $year,
            'status' => InvoiceStatus::Draft,
            'type' => 'sales',
            'document_type' => $this->document_type,
            'withholding_tax_enabled' => $this->withholding_tax_enabled,
            'withholding_tax_percent' => $this->withholding_tax_enabled ? $this->withholding_tax_percent : null,
            'fund_enabled' => $this->fund_enabled,
            'fund_type' => $this->fund_enabled ? $this->fund_type : null,
            'fund_percent' => $this->fund_enabled ? $this->fund_percent : null,
            'fund_vat_rate' => $this->fund_enabled ? $this->fund_vat_rate : null,
            'fund_has_deduction' => $this->fund_enabled && $this->fund_has_deduction,
            'stamp_duty_applied' => $this->stamp_duty_applied,
            'stamp_duty_amount' => $this->stamp_duty_applied ? 200 : 0, // €2.00 in cents
            'payment_method' => $this->payment_method ?: null,
            'payment_terms' => $this->payment_terms ?: null,
            'bank_name' => $this->bank_name ?: null,
            'bank_iban' => $this->bank_iban ?: null,
            'vat_payability' => $this->split_payment ? 'S' : $this->vat_payability,
            'split_payment' => $this->split_payment,
            'notes' => $this->notes ?: null,
        ]);

        foreach ($this->lines as $line) {
            $invoice->lines()->create($this->buildLinePayload($line));
        }

        // Recalculate totals
        $invoice->calculateTotals();

        Cache::forget('invoice_draft_'.auth()->id());
        $this->success(__('app.invoices.created'));
        $this->redirect('/sell-invoices', navigate: true);
    }

    public function saveDraft(): void
    {
        // Only save if the user has started filling the form (at least a contact selected or a line added)
        $hasContent = $this->contact_id || ! empty(array_filter($this->lines, fn ($l) => ! empty($l['description'])));
        if (! $hasContent) {
            return;
        }

        $draft = [];
        foreach ((new \ReflectionClass($this))->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $name = $prop->getName();
            // Skip framework properties and computed properties
            if (in_array($name, ['draftSavedAt', 'totalNet', 'totalVat', 'totalDue', 'netDue', 'withholdingTaxAmount', 'fundAmount', 'fundVatAmount', 'totalsByVatRate'])) {
                continue;
            }
            if ($prop->isStatic() || $prop->isReadOnly()) {
                continue;
            }
            $draft[$name] = $this->{$name};
        }

        Cache::put('invoice_draft_'.auth()->id(), $draft, now()->addHours(24));
        $this->draftSavedAt = now()->format('H:i');
    }

    public function render()
    {
        return view('livewire.invoices.create', [
            'contacts' => Contact::orderBy('name')->get(),
            'sequenceName' => Sequence::find($this->sequence_id)?->name,
            'vatRates' => VatRate::options(),
        ]);
    }
}
