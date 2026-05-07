<?php

namespace App\Livewire\CreditNotes;

use App\Enums\InvoiceStatus;
use App\Enums\VatRate;
use App\Models\Contact;
use App\Models\CreditNote;
use App\Models\Sequence;
use App\Settings\InvoiceSettings;
use Carbon\Carbon;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Mary\Traits\Toast;

class Create extends Component
{
    use Toast;

    #[Validate('required')]
    public ?string $number = null;

    #[Validate('required|date')]
    public string $date = '';

    #[Validate('required|exists:contacts,id')]
    public ?int $contact_id = null;

    #[Validate('required|exists:sequences,id')]
    public ?int $sequence_id = null;

    public ?string $notes = null;

    // Optional reference to the original invoice (DatiFattureCollegate in XML)
    public ?string $related_invoice_number = null;

    public ?string $related_invoice_date = null;

    public array $lines = [];

    public function updatedSequenceId(): void
    {
        $sequence = Sequence::find($this->sequence_id);
        if ($sequence) {
            $this->number = $sequence->getFormattedNumber();
        }
    }

    public function mount(): void
    {
        // Prevent creating credit notes in past fiscal years
        if (session('fiscal_year', now()->year) < now()->year) {
            $this->redirectRoute('credit-notes.index', navigate: true);

            return;
        }

        $this->date = now()->format('Y-m-d');

        // Default sequence for credit notes (prefer system, fallback to first available)
        $invoiceSettings = app(InvoiceSettings::class);
        $defaultSequenceId = $invoiceSettings->default_sequence_credit_notes ?? null;
        $defaultSequence = $defaultSequenceId
            ? Sequence::find($defaultSequenceId)
            : Sequence::where('type', 'credit_note')->orderByDesc('is_system')->first();

        if ($defaultSequence) {
            $this->sequence_id = $defaultSequence->id;
            $this->updatedSequenceId();
        }

        $this->addLine();
    }

    public function addLine(): void
    {
        $this->lines[] = [
            'description' => '',
            'quantity' => 1,
            'unit_of_measure' => '',
            'unit_price' => 0,
            'vat_rate' => VatRate::R22->value,
            'total' => 0,
        ];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function getTotalNetProperty(): float
    {
        $total = 0;
        foreach ($this->lines as $line) {
            $total += (float) $line['quantity'] * (float) $line['unit_price'];
        }

        return $total;
    }

    public function getTotalVatProperty(): float
    {
        $total = 0;
        foreach ($this->lines as $line) {
            $vatRate = VatRate::tryFrom($line['vat_rate'] ?? '');
            if ($vatRate) {
                $lineTotal = (float) $line['quantity'] * (float) $line['unit_price'];
                $total += $lineTotal * ($vatRate->percent() / 100);
            }
        }

        return $total;
    }

    public function getTotalGrossProperty(): float
    {
        return $this->totalNet + $this->totalVat;
    }

    public function save(): void
    {
        $this->validate();

        // Atomically reserve next number to prevent duplicates
        $sequence = Sequence::find($this->sequence_id);
        $year = Carbon::parse($this->date)->year;
        $reserved = $sequence->reserveNextNumber($year);

        $creditNote = CreditNote::create([
            'number' => $reserved['formatted_number'],
            'sequential_number' => $reserved['sequential_number'],
            'date' => $this->date,
            'contact_id' => $this->contact_id,
            'sequence_id' => $this->sequence_id,
            'fiscal_year' => $year,
            'notes' => $this->notes,
            'related_invoice_number' => $this->related_invoice_number ?: null,
            'related_invoice_date' => $this->related_invoice_date ?: null,
            'status' => InvoiceStatus::Draft,
        ]);

        foreach ($this->lines as $line) {
            $lineTotal = (float) $line['quantity'] * (float) $line['unit_price'];

            $creditNote->lines()->create([
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_of_measure' => $line['unit_of_measure'] ?: null,
                'unit_price' => (int) round($line['unit_price'] * 100),
                'vat_rate' => $line['vat_rate'],
                'total' => (int) round($lineTotal * 100),
            ]);
        }

        $creditNote->calculateTotals();

        $this->success(__('app.credit_notes.created'), redirectTo: route('credit-notes.index'));
    }

    public function render()
    {
        return view('livewire.credit-notes.create', [
            'contacts' => Contact::orderBy('name')->get(),
            'sequences' => Sequence::where('type', 'credit_note')->orderBy('name')->get(),
            'sequenceName' => Sequence::find($this->sequence_id)?->name,
            'vatRates' => VatRate::options(),
        ]);
    }
}
