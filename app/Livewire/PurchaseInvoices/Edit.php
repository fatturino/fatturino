<?php

namespace App\Livewire\PurchaseInvoices;

use App\Enums\SdiStatus;
use App\Enums\VatRate;
use App\Livewire\Traits\HasPaymentTracking;
use App\Models\Contact;
use App\Models\PurchaseInvoice;
use App\Models\Sequence;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Validate;
use Livewire\Component;
use App\Traits\Toast;

class Edit extends Component
{
    use HasPaymentTracking, Toast;

    public PurchaseInvoice $purchaseInvoice;

    // True when the invoice cannot be modified (past year or SDI locked)
    public bool $isReadOnly = false;

    // True when the invoice is locked by SDI submission
    public bool $isSdiLocked = false;

    // True when the invoice was received from SDI (passive invoice)
    public bool $isFromSdi = false;

    #[Validate('required')]
    public ?string $number = null;

    #[Validate('required|date')]
    public string $date = '';

    #[Validate('required|exists:contacts,id')]
    public ?int $contact_id = null;

    #[Validate('required|exists:sequences,id')]
    public ?int $sequence_id = null;

    public array $lines = [];

    public function mount(PurchaseInvoice $purchaseInvoice): void
    {
        $this->purchaseInvoice = $purchaseInvoice;
        $this->isSdiLocked = ! $purchaseInvoice->isSdiEditable();
        $this->isFromSdi = $purchaseInvoice->sdi_status === SdiStatus::Received;
        $this->isReadOnly = $purchaseInvoice->date->year < now()->year || $this->isSdiLocked;

        $this->fill($purchaseInvoice->only(['number', 'date', 'contact_id', 'sequence_id']));
        $this->date = $purchaseInvoice->date->format('Y-m-d');

        foreach ($purchaseInvoice->lines as $line) {
            $this->lines[] = [
                'id' => $line->id,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_of_measure' => $line->unit_of_measure ?? '',
                'unit_price' => $line->unit_price / 100, // Convert from cents
                'vat_rate' => $line->vat_rate?->value,
                'total' => $line->total / 100, // Convert from cents
            ];
        }
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

    protected function getPayableInvoice(): Model
    {
        return $this->purchaseInvoice;
    }

    public function save(): void
    {
        if ($this->isReadOnly) {
            $this->error(__('app.purchase_invoices.readonly_error'));

            return;
        }

        $this->validate();

        $this->purchaseInvoice->update([
            'number' => $this->number,
            'date' => $this->date,
            'contact_id' => $this->contact_id,
            'sequence_id' => $this->sequence_id,
        ]);

        // Delete and recreate lines to sync changes
        $this->purchaseInvoice->lines()->delete();

        foreach ($this->lines as $line) {
            $lineTotal = (float) $line['quantity'] * (float) $line['unit_price'];

            $this->purchaseInvoice->lines()->create([
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_of_measure' => $line['unit_of_measure'] ?: null,
                'unit_price' => (int) round($line['unit_price'] * 100), // Convert to cents
                'vat_rate' => $line['vat_rate'],
                'total' => (int) round($lineTotal * 100), // Convert to cents
            ]);
        }

        $this->purchaseInvoice->calculateTotals();

        $this->success(__('app.purchase_invoices.updated'));
        $this->redirect(route('purchase-invoices.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.purchase-invoices.edit', [
            'contacts' => Contact::orderBy('name')->get(),
            'sequences' => Sequence::where('type', 'purchase')->orderBy('name')->get(),
            'vatRates' => VatRate::options(),
            'isReadOnly' => $this->isReadOnly,
        ]);
    }
}
