<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\SdiStatus;
use App\Models\Traits\HasPayments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SelfInvoice extends Model
{
    use HasFactory;
    use HasPayments;

    protected $table = 'invoices';

    protected $guarded = [];

    protected $attributes = [
        'status'         => 'draft',
        'payment_status' => 'unpaid',
    ];

    protected $casts = [
        'date'                    => 'date',
        'related_invoice_date'    => 'date',
        'due_date'                => 'date',
        'status'                  => InvoiceStatus::class,
        'payment_status'          => PaymentStatus::class,
        'sdi_status'              => SdiStatus::class,
        'total_net'               => 'integer',
        'total_vat'               => 'integer',
        'total_gross'             => 'integer',
        'total_paid'              => 'integer',
        'withholding_tax_enabled' => 'boolean',
        'withholding_tax_percent' => 'decimal:2',
        'withholding_tax_amount'  => 'integer',
        'split_payment'           => 'boolean',
        'stamp_duty_applied'      => 'boolean',
        'stamp_duty_amount'       => 'integer',
    ];

    protected static function booted(): void
    {
        // Only return self-invoices (autofatture)
        static::addGlobalScope('self_invoice', function (Builder $query) {
            $query->where('type', 'self_invoice');
        });

        // Auto-set type and fiscal_year on creation
        static::creating(function (self $invoice) {
            $invoice->type = 'self_invoice';
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
     * Whether the invoice payment is overdue based on due_date and payment status.
     * Considers both unpaid and partially paid invoices past their due date.
     */
    public function isOverdue(): bool
    {
        $isUnpaidOrPartial = in_array($this->payment_status, [PaymentStatus::Unpaid, PaymentStatus::Partial]);

        return $isUnpaidOrPartial && $this->due_date?->isPast();
    }

    /**
     * Whether the invoice can be edited (not locked by SDI).
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
     * Recalculate totals from line items.
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

        $withholdingTaxAmount = 0;
        if ($this->withholding_tax_enabled && $this->withholding_tax_percent) {
            $withholdingTaxAmount = (int) round($net * ($this->withholding_tax_percent / 100));
        }

        $this->update([
            'total_net'              => $net,
            'total_vat'              => $vat,
            'total_gross'            => $net + $vat,
            'withholding_tax_amount' => $withholdingTaxAmount,
        ]);
    }
}
