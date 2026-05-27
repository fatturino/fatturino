<?php

namespace App\Models;

use App\Enums\VatRate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FiscalDocumentLine extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'fiscal_documents_lines';

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'integer',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'integer',
        'total' => 'integer',
        'vat_rate' => VatRate::class,
    ];

    protected static function booted()
    {
        static::saved(function (FiscalDocumentLine $line) {
            $line->fiscalDocument->calculateTotals();
        });

        static::deleted(function (FiscalDocumentLine $line) {
            $line->fiscalDocument->calculateTotals();
        });
    }

    public function fiscalDocument()
    {
        return $this->belongsTo(FiscalDocument::class, 'fiscal_document_id')->withoutGlobalScopes();
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function vatRateValue(): string
    {
        $vatRate = $this->vat_rate;

        if ($vatRate instanceof \BackedEnum) {
            return (string) $vatRate->value;
        }

        return (string) $vatRate;
    }
}
