<?php

namespace App\Livewire\SelfInvoices;

use App\Enums\InvoiceStatus;
use App\Enums\VatRate;
use App\Models\Contact;
use App\Models\SelfInvoice;
use App\Models\Sequence;
use App\Services\SelfInvoiceXmlService;
use App\Traits\Toast;
use Carbon\Carbon;
use Livewire\Attributes\Validate;
use Livewire\Component;

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

    // Self-invoice specific fields
    #[Validate('required|in:TD17,TD18,TD19,TD28,TD29')]
    public string $document_type = 'TD17';

    #[Validate('required')]
    public ?string $related_invoice_number = null;

    #[Validate('required|date')]
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
        // Prevent creating self-invoices when a past fiscal year is selected
        if (session('fiscal_year', now()->year) < now()->year) {
            $this->redirectRoute('self-invoices.index', navigate: true);

            return;
        }

        $this->date = now()->format('Y-m-d');

        // Default sequence for self-invoices (prefer system, fallback to first available)
        $defaultSequence = Sequence::where('type', 'self_invoice')
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

    /**
     * Livewire hook called after $lines is updated via wire:model.
     * Ensures computed totals are recalculated during render.
     */
    public function updatedLines(): void
    {
        // Computed properties (totalNet, totalVat, totalGross) are recalculated during render.
        // This hook exists to guarantee Livewire detects the change and re-renders.
    }

    public function getTotalNetProperty(): float
    {
        $total = 0;
        foreach ($this->lines as $line) {
            // Cast to float: wire:model binds values as strings, which causes TypeError in PHP 8
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

        $invoice = SelfInvoice::create([
            'number' => $reserved['formatted_number'],
            'sequential_number' => $reserved['sequential_number'],
            'date' => $this->date,
            'contact_id' => $this->contact_id,
            'sequence_id' => $this->sequence_id,
            'fiscal_year' => $year,
            'document_type' => $this->document_type,
            'related_invoice_number' => $this->related_invoice_number,
            'related_invoice_date' => $this->related_invoice_date,
            'status' => InvoiceStatus::Draft,
        ]);

        foreach ($this->lines as $line) {
            $lineTotal = (float) $line['quantity'] * (float) $line['unit_price'];

            $invoice->lines()->create([
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_of_measure' => $line['unit_of_measure'] ?: null,
                'unit_price' => (int) round($line['unit_price'] * 100),
                'vat_rate' => $line['vat_rate'],
                'total' => (int) round($lineTotal * 100),
            ]);
        }

        $invoice->calculateTotals();

        $this->success(__('app.self_invoices.created'));
        $this->redirect(route('self-invoices.index'), navigate: true);
    }

    public function documentTypeOptions(): array
    {
        return collect(SelfInvoiceXmlService::DOCUMENT_TYPES)
            ->map(fn ($label, $code) => ['id' => $code, 'name' => $label])
            ->values()
            ->all();
    }

    public function render()
    {
        return view('livewire.self-invoices.create', [
            'contacts' => Contact::orderBy('name')->get(),
            'sequenceName' => Sequence::find($this->sequence_id)?->name,
            'vatRates' => VatRate::options(),
            'documentTypeOptions' => $this->documentTypeOptions(),
        ]);
    }
}
