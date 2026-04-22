<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTerms;
use App\Enums\ProformaStatus;
use App\Enums\VatRate;
use App\Models\Traits\HasPayments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProformaInvoice extends Model
{
    use HasFactory;
    use HasPayments;

    protected $table = 'invoices';

    protected $guarded = [];

    protected $attributes = [
        'status' => 'draft',
        'payment_status' => 'unpaid',
    ];

    protected $casts = [
        'date' => 'date',
        'due_date' => 'date',
        'status' => ProformaStatus::class,
        'payment_status' => PaymentStatus::class,
        'total_net' => 'integer',
        'total_vat' => 'integer',
        'total_gross' => 'integer',
        'total_paid' => 'integer',
        'withholding_tax_enabled' => 'boolean',
        'withholding_tax_percent' => 'decimal:2',
        'withholding_tax_amount' => 'integer',
        'stamp_duty_applied' => 'boolean',
        'stamp_duty_amount' => 'integer',
        'fund_enabled' => 'boolean',
        'fund_percent' => 'decimal:2',
        'fund_amount' => 'integer',
        'fund_has_deduction' => 'boolean',
        'fund_vat_rate' => VatRate::class,
        'payment_method' => PaymentMethod::class,
        'payment_terms' => PaymentTerms::class,
    ];

    protected static function booted(): void
    {
        // Only return proforma invoices
        static::addGlobalScope('proforma', function (Builder $query) {
            $query->where('type', 'proforma');
        });

        // Auto-set type and fiscal_year on creation
        static::creating(function (self $invoice) {
            $invoice->type = 'proforma';
            if ($invoice->date && ! $invoice->fiscal_year) {
                $invoice->fiscal_year = $invoice->date->year;
            }
        });
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function sequence()
    {
        return $this->belongsTo(Sequence::class);
    }

    public function lines()
    {
        return $this->hasMany(InvoiceLine::class, 'invoice_id');
    }

    /**
     * The sales invoice created from this proforma (if converted).
     */
    public function convertedInvoice()
    {
        return $this->hasOne(Invoice::class, 'proforma_id');
    }

    /**
     * Whether the proforma payment is overdue.
     * Considers both unpaid and partially paid invoices past their due date.
     */
    public function isOverdue(): bool
    {
        $isUnpaidOrPartial = in_array($this->payment_status, [PaymentStatus::Unpaid, PaymentStatus::Partial]);

        return $isUnpaidOrPartial && $this->due_date?->isPast();
    }

    /**
     * Whether the proforma can be converted to a sales invoice.
     * Convertible when status is Draft or Sent and no conversion exists yet.
     */
    public function isConvertible(): bool
    {
        if (! in_array($this->status, [ProformaStatus::Draft, ProformaStatus::Sent])) {
            return false;
        }

        return ! $this->convertedInvoice()->exists();
    }

    /**
     * Group invoice lines by VAT rate for summary display in PDFs.
     * Same logic as Invoice::getVatSummary(). All amounts in cents.
     *
     * @return array<string, array{rate: float, nature: string|null, taxable: int, tax: int}>
     */
    public function getVatSummary(): array
    {
        $summary = [];

        foreach ($this->lines as $line) {
            $key = ($line->vat_rate->percent() ?? 0).'_'.($line->vat_rate->nature() ?? '');

            if (! isset($summary[$key])) {
                $summary[$key] = [
                    'rate' => (float) ($line->vat_rate->percent() ?? 0),
                    'nature' => $line->vat_rate->nature() ?? null,
                    'taxable' => 0,
                    'tax' => 0,
                ];
            }

            $summary[$key]['taxable'] += $line->total;
            $summary[$key]['tax'] += (int) round($line->total * (($line->vat_rate->percent() ?? 0) / 100));
        }

        // Include fund contribution (rivalsa previdenziale) in the correct VAT bucket
        if ($this->fund_enabled && $this->fund_amount > 0) {
            $rate = (float) ($this->fund_vat_rate?->percent() ?? 0);
            $nature = $this->fund_vat_rate?->nature();
            $key = $rate.'_'.($nature ?? '');

            if (! isset($summary[$key])) {
                $summary[$key] = [
                    'rate' => $rate,
                    'nature' => $nature,
                    'taxable' => 0,
                    'tax' => 0,
                ];
            }

            $summary[$key]['taxable'] += $this->fund_amount;
            $summary[$key]['tax'] += (int) round($this->fund_amount * ($rate / 100));
        }

        return $summary;
    }

    /**
     * Recalculate totals from line items (same logic as Invoice).
     */
    public function calculateTotals(): void
    {
        $net = 0;
        $vat = 0;

        foreach ($this->lines as $line) {
            $lineNet = $line->total;
            $lineVat = (int) round($lineNet * ($line->vat_rate->percent() / 100));
            $net += $lineNet;
            $vat += $lineVat;
        }

        // Fund contribution (rivalsa previdenziale)
        $fundAmount = 0;
        $fundVat = 0;
        if ($this->fund_enabled && $this->fund_percent) {
            $fundAmount = (int) round($net * ($this->fund_percent / 100));
            if ($this->fund_vat_rate) {
                $fundVat = (int) round($fundAmount * ($this->fund_vat_rate->percent() / 100));
            }
        }

        // Withholding tax on original net only
        $withholdingTaxAmount = 0;
        if ($this->withholding_tax_enabled && $this->withholding_tax_percent) {
            $withholdingTaxAmount = (int) round($net * ($this->withholding_tax_percent / 100));
        }

        $this->update([
            'total_net' => $net,
            'total_vat' => $vat + $fundVat,
            'total_gross' => $net + $fundAmount + $vat + $fundVat,
            'fund_amount' => $fundAmount,
            'withholding_tax_amount' => $withholdingTaxAmount,
        ]);
    }
}
