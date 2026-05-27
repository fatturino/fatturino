<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EiInboundLog extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'raw_payload' => 'array',
        'processed_at' => 'datetime',
    ];

    public function fiscalDocument()
    {
        return $this->belongsTo(FiscalDocument::class, 'linked_fiscal_document_id');
    }
}
