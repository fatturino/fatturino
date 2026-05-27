<?php

namespace App\Models;

use App\Enums\SdiStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EiOutboundLog extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'status' => SdiStatus::class,
        'raw_payload' => 'array',
    ];

    public function fiscalDocument()
    {
        return $this->belongsTo(FiscalDocument::class);
    }
}
