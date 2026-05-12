<?php

namespace App\Models;

use App\Contracts\HasTimeline;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTerms;
use App\Enums\SdiStatus;
use App\Enums\VatRate;
use App\Models\Traits\HasPayments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;

class Invoice extends Model implements HasTimeline
{
    use Auditable;
    use HasFactory;
    use HasPayments;

    protected $guarded = [];

    /**
     * Columns excluded from audit: recalculated by calculateTotals() cascade
     * when an InvoiceLine is saved/deleted. Auditing them would create noise.
     */
    protected $auditExclude = [
        'total_net',
        'total_vat',
        'total_gross',
        'total_paid',
        'fund_amount',
        'withholding_tax_amount',
        'updated_at',
    ];

    /**
     * Custom events dispatched via AuditCustom for non-Eloquent actions.
     */
    protected $auditEvents = [
        'created',
        'updated',
        'deleted',
        'email_sent',
        'sdi_sent',
        'sdi_accepted',
        'sdi_rejected',
    ];

    protected $attributes = [
        'status' => 'draft',
        'payment_status' => 'unpaid',
    ];

    protected static function booted(): void
    {
        // Only return sales invoices
        static::addGlobalScope('sales', function (Builder $query) {
            $query->where('type', 'sales');
        });

        // Auto-set type and fiscal_year on creation
        static::creating(function (self $invoice) {
            $invoice->type = 'sales';
            if ($invoice->date && ! $invoice->fiscal_year) {
                $invoice->fiscal_year = $invoice->date->year;
            }
        });
    }

    protected $casts = [
        'date' => 'date',
        'due_date' => 'date',
        'status' => InvoiceStatus::class,
        'payment_status' => PaymentStatus::class,
        'sdi_status' => SdiStatus::class,
        'total_net' => 'integer',
        'total_vat' => 'integer',
        'total_gross' => 'integer',
        'total_paid' => 'integer',
        'withholding_tax_enabled' => 'boolean',
        'withholding_tax_percent' => 'decimal:2',
        'withholding_tax_amount' => 'integer',
        'split_payment' => 'boolean',
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
        return $this->hasMany(InvoiceLine::class);
    }

    public function sdiLogs()
    {
        return $this->hasMany(SdiLog::class);
    }

    /**
     * The proforma invoice this was converted from (if any).
     */
    public function proformaInvoice()
    {
        return $this->belongsTo(ProformaInvoice::class, 'proforma_id');
    }

    /**
     * Whether the invoice payment is overdue based on due_date and payment status.
     * Considers both unpaid and partially paid invoices past their due date.
     */
    public function isOverdue(): bool
    {
        $isUnpaidOrPartial = in_array($this->payment_status, [PaymentStatus::Unpaid, PaymentStatus::Partial]);

        return $isUnpaidOrPartial && $this->due_date?->isPast();
    }

    /**
     * Whether the invoice can be edited (not locked by SDI submission).
     * Editable when: never sent, rejected (NS), or error.
     */
    public function isSdiEditable(): bool
    {
        if ($this->sdi_status === null) {
            return true;
        }

        return $this->sdi_status->isEditable();
    }

    /**
     * Group invoice lines by VAT rate for summary display in PDFs and XML.
     * Mirrors the DatiRiepilogo grouping used in InvoiceXmlService.
     * All returned amounts are in cents.
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

    public function calculateTotals()
    {
        $net = 0; // Line totals in cents
        $vat = 0; // Line VAT in cents

        // Calculate net total and VAT from invoice lines
        foreach ($this->lines as $line) {
            $lineNet = $line->total;
            $lineVat = (int) round($lineNet * ($line->vat_rate->percent() / 100));
            $net += $lineNet;
            $vat += $lineVat;
        }

        // Calculate fund contribution (rivalsa previdenziale) if enabled
        $fundAmount = 0;
        $fundVat = 0;
        if ($this->fund_enabled && $this->fund_percent) {
            // Rivalsa is calculated on the original net (imponibile)
            $fundAmount = (int) round($net * ($this->fund_percent / 100));
            if ($this->fund_vat_rate) {
                $fundVat = (int) round($fundAmount * ($this->fund_vat_rate->percent() / 100));
            }
        }

        // Withholding tax on original net only (excluding fund contribution)
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
