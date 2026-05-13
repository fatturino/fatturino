<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\SdiStatus;
use App\Models\Traits\HasPayments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseInvoice extends Model
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
        'sdi_received_at' => 'datetime',
        'sdi_synced_at' => 'datetime',
        'sdi_payload' => 'array',
        'sdi_processed' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Only return purchase invoices
        static::addGlobalScope('purchase', function (Builder $query) {
            $query->where('type', 'purchase');
        });

        // Auto-set type and fiscal_year on creation
        static::creating(function (self $invoice) {
            $invoice->type = 'purchase';
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
            'total_net' => $net,
            'total_vat' => $vat,
            'total_gross' => $net + $vat,
            'withholding_tax_amount' => $withholdingTaxAmount,
        ]);
    }

    /** Filter invoices synced from SDI. */
    public function scopeFromSdi(Builder $query): void
    {
        $query->where('source', 'sdi_sync');
    }

    /** Filter invoices within a date range (by invoice date). */
    public function scopeBetweenDates(Builder $query, string $startDate, string $endDate): void
    {
        $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Create or update a PurchaseInvoice from raw OpenAPI SDI payload data.
     * Deduplicates by sdi_uuid (the OpenAPI unique identifier for the invoice).
     *
     * Returns null when the incoming SDI document matches a self-invoice
     * (autofattura) that was already sent by us — the self-invoice is updated
     * to Delivered status instead of creating a duplicate purchase record.
     */
    public static function createOrUpdateFromSdiData(array $invoiceData, Contact $contact): ?self
    {
        $payload = $invoiceData['payload'] ?? [];
        $body = $payload['fattura_elettronica_body'][0] ?? [];
        $documentData = $body['dati_generali']['dati_generali_documento'] ?? [];

        // Calculate totals from summary lines (dati_riepilogo)
        $summaryData = $body['dati_beni_servizi']['dati_riepilogo'] ?? [];
        $taxableAmount = 0;
        $vatAmount = 0;

        foreach ($summaryData as $summary) {
            $taxableAmount += (float) ($summary['imponibile_importo'] ?? 0);
            $vatAmount += (float) ($summary['imposta'] ?? 0);
        }

        $totalAmount = $documentData['importo_totale_documento'] ?? ($taxableAmount + $vatAmount);

        $totalNet = (int) round($taxableAmount * 100);
        $totalVat = (int) round($vatAmount * 100);
        $totalGross = (int) round((float) $totalAmount * 100);

        // Check if this document is already registered as a purchase invoice
        $existing = self::withoutGlobalScopes()
            ->where('type', 'purchase')
            ->where('sdi_uuid', $invoiceData['uuid'])
            ->first();

        if ($existing) {
            $existing->update(['sdi_synced_at' => now()]);

            return $existing;
        }

        // Check if this document matches a self-invoice we previously sent.
        // When a self-invoice is sent to SDI and then received back as a
        // purchase (since we are also the recipient), we should NOT create a
        // duplicate purchase row — instead mark the self-invoice as delivered.
        //
        // Primary match: by file_id (assigned by SDI, same for both events).
        // Fallback: by document number + date (for self-invoices sent before
        // this fix that don't have sdi_file_id populated yet).
        $incomingFileId = $invoiceData['file_id'] ?? null;
        $documentNumber = $documentData['numero'] ?? null;
        $documentDate = $documentData['data'] ?? null;

        $selfInvoice = null;

        if ($incomingFileId) {
            $selfInvoice = \App\Models\SelfInvoice::withoutGlobalScopes()
                ->where('sdi_file_id', $incomingFileId)
                ->first();
        }

        // Fallback: match by document number + date for pre-fix self-invoices
        if (! $selfInvoice && $documentNumber && $documentDate) {
            $selfInvoice = \App\Models\SelfInvoice::withoutGlobalScopes()
                ->where('number', $documentNumber)
                ->where('date', $documentDate)
                ->where('sdi_status', \App\Enums\SdiStatus::Sent)
                ->first();
        }

        if ($selfInvoice) {
            $selfInvoice->update([
                'sdi_file_id' => $selfInvoice->sdi_file_id ?: $incomingFileId,
                'sdi_status' => \App\Enums\SdiStatus::Delivered,
                'sdi_message' => 'Consegnata (ricevuta come acquisto)',
            ]);

            return null;
        }

        return self::create([
            'contact_id' => $contact->id,
            'number' => $documentData['numero'] ?? $invoiceData['uuid'],
            'date' => $documentData['data'] ?? now()->toDateString(),
            'document_type' => $documentData['tipo_documento'] ?? 'TD01',
            'total_net' => $totalNet,
            'total_vat' => $totalVat,
            'total_gross' => $totalGross,
            'sdi_uuid' => $invoiceData['uuid'],
            'sdi_filename' => $invoiceData['filename'] ?? null,
            'sdi_file_id' => $invoiceData['file_id'] ?? null,
            'sdi_received_at' => $invoiceData['created_at'] ?? now(),
            'sdi_synced_at' => now(),
            'sdi_payload' => $payload,
            'sdi_processed' => false,
            'source' => 'sdi_sync',
        ]);
    }
}
