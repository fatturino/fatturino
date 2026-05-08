<?php

namespace App\Livewire\PurchaseInvoices;

use App\Enums\InvoiceStatus;
use App\Enums\VatRate;
use App\Models\Contact;
use App\Models\PurchaseInvoice;
use App\Models\Sequence;
use Carbon\Carbon;
use Livewire\Attributes\Validate;
use Livewire\Component;
use App\Traits\Toast;

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
        // Prevent creating purchases when a past fiscal year is selected
        if (session('fiscal_year', now()->year) < now()->year) {
            $this->redirectRoute('purchase-invoices.index', navigate: true);

            return;
        }

        $this->date = now()->format('Y-m-d');

        // Default sequence for purchases (prefer system, fallback to first available)
        $defaultSequence = Sequence::where('type', 'purchase')
            ->orderByDesc('is_system')
            ->first();
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

        $invoice = PurchaseInvoice::create([
            'number' => $reserved['formatted_number'],
            'sequential_number' => $reserved['sequential_number'],
            'date' => $this->date,
            'contact_id' => $this->contact_id,
            'sequence_id' => $this->sequence_id,
            'fiscal_year' => $year,
            'status' => InvoiceStatus::Draft,
        ]);

        foreach ($this->lines as $line) {
            $lineTotal = (float) $line['quantity'] * (float) $line['unit_price'];

            $invoice->lines()->create([
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_of_measure' => $line['unit_of_measure'] ?: null,
                'unit_price' => (int) round($line['unit_price'] * 100), // Convert to cents
                'vat_rate' => $line['vat_rate'],
                'total' => (int) round($lineTotal * 100), // Convert to cents
            ]);
        }

        $invoice->calculateTotals();

        $this->success(__('app.purchase_invoices.created'), redirectTo: route('purchase-invoices.index'));
    }

    public function render()
    {
        return view('livewire.purchase-invoices.create', [
            'contacts' => Contact::orderBy('name')->get(),
            'sequences' => Sequence::where('type', 'purchase')->orderBy('name')->get(),
            'vatRates' => VatRate::options(),
        ]);
    }
}
