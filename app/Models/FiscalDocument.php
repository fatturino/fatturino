<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\SdiStatus;
use App\Enums\VatRate;
use Carbon\CarbonInterface;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Parental\HasChildren;

class FiscalDocument extends Model
{
    use HasChildren;
    use HasFactory;

    protected $table = 'fiscal_documents';

    protected $guarded = [];

    protected $appends = [
        'net_due',
        'overpaid_amount',
    ];

    protected $casts = [
        'date' => 'date',
        'due_date' => 'date',
        'payment_status' => PaymentStatus::class,
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
        'fund_vat_rate' => VatRate::class,
        'business_fingerprint' => 'string',
        'metadata' => 'array',
    ];

    protected $childTypes = [
        'sales' => SalesInvoice::class,
        'purchase' => PurchaseInvoice::class,
        'self_invoice' => SelfInvoice::class,
        'credit_note' => CreditNote::class,
        'proforma' => ProformaInvoice::class,
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(Sequence::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FiscalDocumentLine::class, 'fiscal_document_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'fiscal_document_id');
    }

    public function sdiLogs(): HasMany
    {
        return $this->hasMany(EiOutboundLog::class, 'fiscal_document_id');
    }

    protected static function booted(): void
    {
        static::creating(function (self $document) {
            if (empty($document->public_id)) {
                $document->public_id = (string) Str::ulid();
            }

            $sequenceType = null;
            if (! empty($document->sequence_id)) {
                $sequenceType = Sequence::query()
                    ->whereKey($document->sequence_id)
                    ->value('type');
            }

            if (empty($document->type)) {
                $document->type = $sequenceType ?: 'sales';
            }

            if ($document->type === 'sales') {
                $document->type = 'sales';
            }

            if ($document->date && ! $document->fiscal_year) {
                $document->fiscal_year = $document->date->year;
            }
        });
    }

    public function getNetDueAttribute(): int
    {
        $due = $this->total_gross + ($this->stamp_duty_amount ?? 0) - ($this->withholding_tax_amount ?? 0);
        $fundVatRate = $this->fund_vat_rate instanceof VatRate
            ? $this->fund_vat_rate
            : VatRate::tryFrom((string) $this->fund_vat_rate);

        if ($this->split_payment) {
            $due -= $this->total_vat;
            if ($this->fund_enabled && $this->fund_amount > 0 && $fundVatRate) {
                $due += (int) round($this->fund_amount * ($fundVatRate->percent() / 100));
            }
        }

        return max(0, $due);
    }

    public function getOverpaidAmountAttribute(): int
    {
        return max(0, ($this->total_paid ?? 0) - $this->net_due);
    }

    public function calculateTotals(): void
    {
        $this->refresh();

        $net = 0;
        $vat = 0;

        foreach ($this->lines as $line) {
            $lineNet = $line->total ?? 0;
            $lineVat = (int) round($lineNet * (($line->vat_rate?->percent() ?? 0) / 100));
            $net += $lineNet;
            $vat += $lineVat;
        }

        $fundAmount = 0;
        $fundVat = 0;
        $fundVatRate = $this->fund_vat_rate instanceof VatRate
            ? $this->fund_vat_rate
            : VatRate::tryFrom((string) $this->fund_vat_rate);
        if ($this->fund_enabled && $this->fund_percent) {
            $fundAmount = (int) round($net * ($this->fund_percent / 100));
            if ($fundVatRate) {
                $fundVat = (int) round($fundAmount * ($fundVatRate->percent() / 100));
            }
        }

        $withholdingTaxAmount = 0;
        if ($this->withholding_tax_enabled && $this->withholding_tax_percent) {
            $withholdingTaxAmount = (int) round($net * ($this->withholding_tax_percent / 100));
        }

        $this->forceFill([
            'total_net' => $net,
            'total_vat' => $vat + $fundVat,
            'total_gross' => $net + $fundAmount + $vat + $fundVat,
            'fund_amount' => $fundAmount,
            'withholding_tax_amount' => $withholdingTaxAmount,
        ])->save();
    }

    public function recalculatePaymentStatus(): void
    {
        $totalPaid = (int) $this->payments()->sum('amount');

        $status = match (true) {
            $totalPaid <= 0 => PaymentStatus::Unpaid,
            $totalPaid < $this->net_due => PaymentStatus::Partial,
            default => PaymentStatus::Paid,
        };

        if (in_array($status, [PaymentStatus::Unpaid, PaymentStatus::Partial], true) && ! empty($this->due_date)) {
            $dueDate = $this->due_date instanceof CarbonInterface
                ? $this->due_date
                : $this->asDateTime($this->due_date);

            if ($dueDate->isPast()) {
                $status = PaymentStatus::Overdue;
            }
        }

        $this->forceFill([
            'total_paid' => $totalPaid,
            'payment_status' => $status,
        ])->save();
    }

    public function remainingBalance(): int
    {
        return max(0, $this->net_due - ((int) $this->total_paid));
    }

    public function isSdiEditable(): bool
    {
        if (empty($this->sdi_status)) {
            return true;
        }

        $status = $this->sdi_status instanceof SdiStatus
            ? $this->sdi_status
            : SdiStatus::tryFrom((string) $this->sdi_status);

        return $status?->isEditable() ?? false;
    }

    /**
     * Whether the document payment is overdue based on due_date and payment status.
     * Considers unpaid and partially paid documents past their due date.
     */
    public function isOverdue(): bool
    {
        $isUnpaidOrPartial = in_array(
            $this->paymentStatusValue(),
            [PaymentStatus::Unpaid->value, PaymentStatus::Partial->value],
            true
        );

        if (! $isUnpaidOrPartial || empty($this->due_date)) {
            return false;
        }

        if ($this->due_date instanceof CarbonInterface) {
            return $this->due_date->isPast();
        }

        $parsedDueDate = $this->asDateTime($this->due_date);

        return $parsedDueDate->isPast();
    }

    public function statusValue(): string
    {
        $status = $this->status;

        if ($status instanceof \BackedEnum) {
            return (string) $status->value;
        }

        return (string) $status;
    }

    public function paymentStatusValue(): string
    {
        $status = $this->payment_status;

        if ($status instanceof \BackedEnum) {
            return (string) $status->value;
        }

        return (string) $status;
    }

    /**
     * Return VAT breakdown grouped by rate and nature. All amounts are in cents.
     *
     * @return array<string, array{rate: float, nature: string|null, taxable: int, tax: int}>
     */
    public function getVatSummary(): array
    {
        $summary = [];

        foreach ($this->lines as $line) {
            $rate = (float) ($line->vat_rate?->percent() ?? 0);
            $nature = $line->vat_rate?->nature();
            $key = $rate.'_'.($nature ?? '');

            if (! isset($summary[$key])) {
                $summary[$key] = [
                    'rate' => $rate,
                    'nature' => $nature,
                    'taxable' => 0,
                    'tax' => 0,
                ];
            }

            $lineTotal = (int) ($line->total ?? 0);
            $summary[$key]['taxable'] += $lineTotal;
            $summary[$key]['tax'] += (int) round($lineTotal * ($rate / 100));
        }

        if ($this->fund_enabled && (int) $this->fund_amount > 0) {
            $fundVatRate = $this->fund_vat_rate instanceof VatRate
                ? $this->fund_vat_rate
                : VatRate::tryFrom((string) $this->fund_vat_rate);
            $fundRate = (float) ($fundVatRate?->percent() ?? 0);
            $fundNature = $fundVatRate?->nature();
            $fundKey = $fundRate.'_'.($fundNature ?? '');

            if (! isset($summary[$fundKey])) {
                $summary[$fundKey] = [
                    'rate' => $fundRate,
                    'nature' => $fundNature,
                    'taxable' => 0,
                    'tax' => 0,
                ];
            }

            $fundAmount = (int) $this->fund_amount;
            $summary[$fundKey]['taxable'] += $fundAmount;
            $summary[$fundKey]['tax'] += (int) round($fundAmount * ($fundRate / 100));
        }

        return $summary;
    }

    protected static function newFactory()
    {
        return InvoiceFactory::new();
    }
}
