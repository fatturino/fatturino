<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
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
    public function fiscalDocument(): BelongsTo
    {
        return $this->belongsTo(FiscalDocument::class, 'fiscal_document_id');
    }
}
