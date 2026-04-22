<?php

namespace App\Models;

use App\Enums\SdiStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SdiLog extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'status' => SdiStatus::class,
        'raw_payload' => 'array',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
