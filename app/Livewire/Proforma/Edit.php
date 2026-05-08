<?php

namespace App\Livewire\Proforma;

use App\Actions\ConvertProformaToInvoice;
use App\Enums\FundType;
use App\Enums\ProformaStatus;
use App\Enums\VatRate;
use App\Livewire\Traits\HasEmailSending;
use App\Livewire\Traits\HasPaymentTracking;
use App\Models\Contact;
use App\Models\ProformaInvoice;
use App\Models\Sequence;
use App\Services\CourtesyPdfService;
use App\Settings\InvoiceSettings;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Validate;
use Livewire\Component;
use App\Traits\Toast;

class Edit extends Component
{
    use HasEmailSending, HasPaymentTracking, Toast;

    public ProformaInvoice $proformaInvoice;

    public bool $isReadOnly = false;

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

    public function mount(ProformaInvoice $proformaInvoice)
    {
        $this->proformaInvoice = $proformaInvoice;

        // Read-only when: past fiscal year, converted, or cancelled
        $this->isReadOnly = $proformaInvoice->date->year < now()->year
            || in_array($proformaInvoice->status, [ProformaStatus::Converted, ProformaStatus::Cancelled]);

        $this->fill($proformaInvoice->only(['number', 'date', 'contact_id', 'sequence_id']));
        $this->date = $proformaInvoice->date->format('Y-m-d');

        // Load withholding tax
        $this->withholding_tax_enabled = $proformaInvoice->withholding_tax_enabled ?? false;
        $this->withholding_tax_percent = $proformaInvoice->withholding_tax_percent ?? '20.00';

        // Load fund
        $this->fund_enabled = $proformaInvoice->fund_enabled ?? false;
        $this->fund_type = $proformaInvoice->fund_type;
        $this->fund_percent = $proformaInvoice->fund_percent ?? '4.00';
        $this->fund_vat_rate = $proformaInvoice->fund_vat_rate?->value;
        $this->fund_has_deduction = $proformaInvoice->fund_has_deduction ?? false;

        // Load stamp duty
        $settings = app(InvoiceSettings::class);
        $this->stamp_duty_applied = $proformaInvoice->stamp_duty_applied ?? false;
        $this->auto_stamp_duty = $settings->auto_stamp_duty;
        $this->stamp_duty_threshold = $settings->stamp_duty_threshold;

        // Load lines (convert cents to euros for display)
        foreach ($proformaInvoice->lines as $line) {
            $this->lines[] = [
                'id' => $line->id,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_of_measure' => $line->unit_of_measure ?? '',
                'unit_price' => $line->unit_price / 100,
                'vat_rate' => $line->vat_rate?->value,
                'total' => $line->total / 100,
            ];
        }
    }

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

    protected function getPayableInvoice(): Model
    {
        return $this->proformaInvoice;
    }

    /**
     * Mark proforma as sent to client, then trigger auto-send email if configured.
     */
    public function markAsSent(): void
    {
        if ($this->proformaInvoice->status !== ProformaStatus::Draft) {
            return;
        }

        $this->proformaInvoice->update(['status' => ProformaStatus::Sent]);
        $this->triggerAutoSend('auto_send_proforma');
        $this->success(__('app.proforma.marked_sent'));
    }

    /**
     * Cancel the proforma.
     */
    public function cancelProforma(): void
    {
        if (! in_array($this->proformaInvoice->status, [ProformaStatus::Draft, ProformaStatus::Sent])) {
            return;
        }

        $this->proformaInvoice->update(['status' => ProformaStatus::Cancelled]);
        $this->isReadOnly = true;
        $this->success(__('app.proforma.cancelled'));
    }

    /**
     * Convert proforma to a sales invoice.
     */
    public function convertToInvoice(): void
    {
        $action = app(ConvertProformaToInvoice::class);
        $invoice = $action->execute($this->proformaInvoice);

        if (! $invoice) {
            $this->error(__('app.proforma.cannot_convert'));

            return;
        }

        $this->success(
            __('app.proforma.converted_success', ['number' => $invoice->number]),
            redirectTo: route('sell-invoices.edit', $invoice)
        );
    }

    public function save()
    {
        if ($this->isReadOnly) {
            $this->error(__('app.proforma.readonly_error'));

            return;
        }

        $this->validate();

        $this->proformaInvoice->update([
            'number' => $this->number,
            'date' => $this->date,
            'contact_id' => $this->contact_id,
            'sequence_id' => $this->sequence_id,
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

        // Sync lines: delete old and recreate
        $this->proformaInvoice->lines()->delete();

        foreach ($this->lines as $line) {
            $lineTotal = (float) $line['quantity'] * (float) $line['unit_price'];

            $this->proformaInvoice->lines()->create([
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_of_measure' => $line['unit_of_measure'] ?: null,
                'unit_price' => (int) round($line['unit_price'] * 100),
                'vat_rate' => $line['vat_rate'],
                'total' => (int) round($lineTotal * 100),
            ]);
        }

        $this->proformaInvoice->calculateTotals();

        $this->success(__('app.proforma.updated'), redirectTo: '/proforma');
    }

    public function downloadPdf(CourtesyPdfService $pdfService)
    {
        try {
            $pdf = $pdfService->generateForProforma($this->proformaInvoice);
            $filename = $pdfService->generateProformaFileName($this->proformaInvoice);

            return response()->streamDownload(
                fn () => print ($pdf->output()),
                $filename,
                ['Content-Type' => 'application/pdf']
            );
        } catch (\Exception $e) {
            $this->error(__('app.invoices.pdf_generation_error', ['error' => $e->getMessage()]));
        }
    }

    protected function getEmailDocument(): Model
    {
        return $this->proformaInvoice;
    }

    protected function getEmailDocumentType(): string
    {
        return 'proforma';
    }

    public function render()
    {
        return view('livewire.proforma.edit', [
            'contacts' => Contact::orderBy('name')->get(),
            'sequences' => Sequence::where('type', 'proforma')->orderBy('name')->get(),
            'vatRates' => VatRate::options(),
            'isReadOnly' => $this->isReadOnly,
            'convertedInvoice' => $this->proformaInvoice->convertedInvoice,
        ]);
    }
}
