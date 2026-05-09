<?php

namespace App\Livewire\Proforma;

use App\Enums\FundType;
use App\Enums\ProformaStatus;
use App\Enums\VatRate;
use App\Models\Contact;
use App\Models\ProformaInvoice;
use App\Models\Sequence;
use App\Settings\InvoiceSettings;
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

    // Lines
    public array $lines = [];

    // Reverse calculation modal (Scorporo)
    public bool $reverseCalcModal = false;

    public string $reverseCalcDesiredNet = '';

    public ?string $reverseCalcVatRate = null;

    public function updatedLines()
    {
        if ($this->auto_stamp_duty) {
            $this->stamp_duty_applied = $this->stampDutyEligible;
        }
    }

    public function updatedFundType()
    {
        if ($this->fund_type) {
            $type = FundType::tryFrom($this->fund_type);
            if ($type) {
                $this->fund_percent = $type->defaultPercent();
            }
        }
    }

    public function updatedSequenceId()
    {
        $sequence = Sequence::find($this->sequence_id);
        if ($sequence) {
            $this->number = $sequence->getFormattedNumber();
        }
    }

    public function mount(InvoiceSettings $settings)
    {
        // Prevent creating proforma when a past fiscal year is selected
        if (session('fiscal_year', now()->year) < now()->year) {
            $this->redirectRoute('proforma.index', navigate: true);

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

        // Initialize stamp duty
        $this->auto_stamp_duty = $settings->auto_stamp_duty;
        $this->stamp_duty_threshold = $settings->stamp_duty_threshold;

        // Set default proforma sequence
        $defaultSequence = Sequence::where('type', 'proforma')
            ->orderByDesc('is_system')
            ->first();
        if ($defaultSequence) {
            $this->sequence_id = $defaultSequence->id;
            $this->updatedSequenceId();
        }

        $this->addLine();
    }

    public function addLine()
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

    public function removeLine($index)
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function getTotalNetProperty()
    {
        $total = 0;
        foreach ($this->lines as $line) {
            $total += (float) $line['quantity'] * (float) $line['unit_price'];
        }

        return $total;
    }

    public function getTotalVatProperty()
    {
        $total = 0;
        foreach ($this->lines as $line) {
            $vatRate = VatRate::tryFrom($line['vat_rate'] ?? '');
            if ($vatRate) {
                $lineTotal = (float) $line['quantity'] * (float) $line['unit_price'];
                $total += $lineTotal * ($vatRate->percent() / 100);
            }
        }

        // Add VAT on fund contribution
        $total += $this->fundVatAmount;

        return $total;
    }

    public function getFundAmountProperty(): float
    {
        if (! $this->fund_enabled || ! $this->fund_percent) {
            return 0.0;
        }

        return $this->totalNet * ((float) $this->fund_percent / 100);
    }

    public function getFundVatAmountProperty(): float
    {
        if ($this->fundAmount <= 0 || ! $this->fund_vat_rate) {
            return 0.0;
        }

        $vatRate = VatRate::tryFrom($this->fund_vat_rate);

        return $vatRate ? $this->fundAmount * ($vatRate->percent() / 100) : 0.0;
    }

    public function getTotalGrossProperty()
    {
        return $this->totalNet + $this->fundAmount + $this->totalVat;
    }

    public function getTotalDueProperty(): float
    {
        $total = $this->totalGross;

        if ($this->stamp_duty_applied) {
            $total += 2.00;
        }

        return $total;
    }

    public function getWithholdingTaxAmountProperty(): float
    {
        if (! $this->withholding_tax_enabled || ! $this->withholding_tax_percent) {
            return 0.0;
        }

        return $this->totalNet * ((float) $this->withholding_tax_percent / 100);
    }

    public function getNetDueProperty(): float
    {
        return $this->totalDue - $this->withholdingTaxAmount;
    }

    public function getStampDutyEligibleProperty(): bool
    {
        return $this->totalGross > (float) $this->stamp_duty_threshold;
    }

    public function openReverseCalcModal()
    {
        if (empty($this->lines)) {
            $this->error(__('app.proforma.reverse_calc_no_lines'));

            return;
        }

        $this->reverseCalcVatRate = $this->lines[0]['vat_rate'] ?? VatRate::R22->value;
        $this->reverseCalcDesiredNet = '';
        $this->reverseCalcModal = true;
    }

    public function getReverseCalcNetProperty(): float
    {
        $desired = (float) $this->reverseCalcDesiredNet;
        if ($desired <= 0) {
            return 0.0;
        }

        $vatPercent = 0;
        if ($this->reverseCalcVatRate) {
            $reverseVatRate = VatRate::tryFrom($this->reverseCalcVatRate);
            $vatPercent = $reverseVatRate ? $reverseVatRate->percent() / 100 : 0;
        }

        $fundPercent = ($this->fund_enabled && $this->fund_percent) ? (float) $this->fund_percent / 100 : 0;

        $fundVatPercent = 0;
        if ($fundPercent > 0 && $this->fund_vat_rate) {
            $fvRate = VatRate::tryFrom($this->fund_vat_rate);
            $fundVatPercent = $fvRate ? $fvRate->percent() / 100 : 0;
        }

        $withholdingPercent = ($this->withholding_tax_enabled && $this->withholding_tax_percent)
            ? (float) $this->withholding_tax_percent / 100
            : 0;

        $stamp = $this->stamp_duty_applied ? 2.00 : 0;

        $multiplier = 1 + $vatPercent + $fundPercent + ($fundPercent * $fundVatPercent) - $withholdingPercent;

        if ($multiplier <= 0) {
            return 0.0;
        }

        return ($desired - $stamp) / $multiplier;
    }

    public function applyReverseCalculation()
    {
        $targetNet = $this->reverseCalcNet;
        if ($targetNet <= 0) {
            return;
        }

        $currentNet = $this->totalNet;

        if ($currentNet > 0) {
            $scale = $targetNet / $currentNet;
            foreach ($this->lines as $index => $line) {
                $this->lines[$index]['unit_price'] = round((float) $line['unit_price'] * $scale, 2);
            }
        } elseif (count($this->lines) === 1) {
            $qty = (float) $this->lines[0]['quantity'] ?: 1;
            $this->lines[0]['unit_price'] = round($targetNet / $qty, 2);
        }

        if ($this->auto_stamp_duty) {
            $this->stamp_duty_applied = $this->stampDutyEligible;
        }

        $this->reverseCalcModal = false;
    }

    public function save()
    {
        $this->validate();

        $sequence = Sequence::find($this->sequence_id);
        $year = Carbon::parse($this->date)->year;
        $reserved = $sequence->reserveNextNumber($year);

        $proforma = ProformaInvoice::create([
            'number' => $reserved['formatted_number'],
            'sequential_number' => $reserved['sequential_number'],
            'date' => $this->date,
            'contact_id' => $this->contact_id,
            'sequence_id' => $this->sequence_id,
            'fiscal_year' => $year,
            'status' => ProformaStatus::Draft,
            'withholding_tax_enabled' => $this->withholding_tax_enabled,
            'withholding_tax_percent' => $this->withholding_tax_enabled ? $this->withholding_tax_percent : null,
            'fund_enabled' => $this->fund_enabled,
            'fund_type' => $this->fund_enabled ? $this->fund_type : null,
            'fund_percent' => $this->fund_enabled ? $this->fund_percent : null,
            'fund_vat_rate' => $this->fund_enabled ? $this->fund_vat_rate : null,
            'fund_has_deduction' => $this->fund_enabled && $this->fund_has_deduction,
            'stamp_duty_applied' => $this->stamp_duty_applied,
            'stamp_duty_amount' => $this->stamp_duty_applied ? 200 : 0,
        ]);

        foreach ($this->lines as $line) {
            $lineTotal = (float) $line['quantity'] * (float) $line['unit_price'];

            $proforma->lines()->create([
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_of_measure' => ($line['unit_of_measure'] ?? null) ?: null,
                'unit_price' => (int) round($line['unit_price'] * 100),
                'vat_rate' => $line['vat_rate'],
                'total' => (int) round($lineTotal * 100),
            ]);
        }

        $proforma->calculateTotals();

        $this->success(__('app.proforma.created'));
        $this->redirect('/proforma', navigate: true);
    }

    public function render()
    {
        return view('livewire.proforma.create', [
            'contacts' => Contact::orderBy('name')->get(),
            'sequenceName' => Sequence::find($this->sequence_id)?->name,
            'vatRates' => VatRate::options(),
        ]);
    }
}
