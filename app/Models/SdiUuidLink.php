<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SdiUuidLink extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function fiscalDocument()
    {
        return $this->belongsTo(FiscalDocument::class);
    }
}
