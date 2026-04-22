<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Payment extends Model implements AuditableContract
{
    use Auditable;
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'amount' => 'integer',
        'paid_at' => 'date',
    ];

    /**
     * The invoice this payment belongs to.
     * All invoice types (sales, purchase, proforma, self-invoice) share the invoices table.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
