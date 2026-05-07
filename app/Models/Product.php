<?php

namespace App\Models;

use App\Enums\VatRate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'price' => 'integer', // Stored in cents
        'vat_rate' => VatRate::class,
    ];
}
