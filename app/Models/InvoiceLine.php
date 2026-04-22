<?php

namespace App\Models;

use App\Enums\VatRate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class InvoiceLine extends Model implements AuditableContract
{
    use Auditable;
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'integer', // Stored in cents
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'integer', // Stored in cents
        'total' => 'integer', // Stored in cents
        'vat_rate' => VatRate::class,
    ];

    protected static function booted()
    {
        static::saved(function (InvoiceLine $line) {
            // Retrieve parent without global scopes (works for both sales and purchase)
            $line->parentInvoice->calculateTotals();
        });

        static::deleted(function (InvoiceLine $line) {
            $line->parentInvoice->calculateTotals();
        });
    }

    /**
     * Relationship without global scopes — used in lifecycle hooks
     * to support both Invoice (sales) and PurchaseInvoice.
     */
    public function parentInvoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id')->withoutGlobalScopes();
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
