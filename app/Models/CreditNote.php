<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTerms;
use App\Enums\SdiStatus;
use App\Models\Traits\HasPayments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditNote extends Model
{
    use HasFactory;
    use HasPayments;

    protected $table = 'invoices';

    protected $guarded = [];

    protected $attributes = [
        'status' => 'draft',
        'payment_status' => 'unpaid',
        'document_type' => 'TD04',
    ];

    protected $casts = [
        'date' => 'date',
        'related_invoice_date' => 'date',
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
        'payment_method' => PaymentMethod::class,
        'payment_terms' => PaymentTerms::class,
    ];

    protected static function booted(): void
    {
        // Only return credit notes (note di credito)
        static::addGlobalScope('credit_note', function (Builder $query) {
            $query->where('type', 'credit_note');
        });

        // Auto-set type, document_type, and fiscal_year on creation
        static::creating(function (self $creditNote) {
            $creditNote->type = 'credit_note';
            $creditNote->document_type = 'TD04';
            if ($creditNote->date && ! $creditNote->fiscal_year) {
                $creditNote->fiscal_year = $creditNote->date->year;
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

    public function sdiLogs()
    {
        return $this->hasMany(SdiLog::class, 'invoice_id');
    }

    /**
     * Whether the credit note payment is overdue based on due_date and payment status.
     */
    public function isOverdue(): bool
    {
        $isUnpaidOrPartial = in_array($this->payment_status, [PaymentStatus::Unpaid, PaymentStatus::Partial]);

        return $isUnpaidOrPartial && $this->due_date?->isPast();
    }

    /**
     * Whether the credit note can be edited (not locked by SDI).
     */
    public function isSdiEditable(): bool
    {
        if ($this->sdi_status === null) {
            return true;
        }

        return $this->sdi_status->isEditable();
    }

    /**
     * Return VAT breakdown grouped by rate and nature. All amounts in cents.
     */
    public function getVatSummary(): array
    {
        $summary = [];

        foreach ($this->lines as $line) {
            $key = ($line->vat_rate?->percent() ?? 0).'_'.($line->vat_rate?->nature() ?? '');

            if (! isset($summary[$key])) {
                $summary[$key] = [
                    'rate' => (float) ($line->vat_rate?->percent() ?? 0),
                    'nature' => $line->vat_rate?->nature() ?? null,
                    'taxable' => 0,
                    'tax' => 0,
                ];
            }

            $summary[$key]['taxable'] += $line->total;
            $summary[$key]['tax'] += (int) round($line->total * (($line->vat_rate?->percent() ?? 0) / 100));
        }

        return array_values($summary);
    }

    /**
     * Recalculate totals from line items.
     */
    public function calculateTotals(): void
    {
        $net = 0;
        $vat = 0;

        foreach ($this->lines as $line) {
            $lineNet = $line->total;
            $lineVat = (int) round($lineNet * (($line->vat_rate?->percent() ?? 0) / 100));
            $net += $lineNet;
            $vat += $lineVat;
        }

        $withholdingTaxAmount = 0;
        if ($this->withholding_tax_enabled && $this->withholding_tax_percent) {
            $withholdingTaxAmount = (int) round($net * ($this->withholding_tax_percent / 100));
        }

        $this->update([
            'total_net' => $net,
            'total_vat' => $vat,
            'total_gross' => $net + $vat,
            'withholding_tax_amount' => $withholdingTaxAmount,
        ]);
    }
}
