<?php

namespace App\Models;

use App\Enums\F24Category;
use App\Enums\F24Section;
use App\Support\F24TributeCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class F24PaymentLine extends Model
{
    use HasFactory;

    protected $table = 'f24_payment_lines';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'section' => F24Section::class,
            'category' => F24Category::class,
            'position' => 'integer',
            'debit_cents' => 'integer',
            'credit_cents' => 'integer',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(F24Payment::class, 'f24_payment_id');
    }

    public function code(): ?string
    {
        if ($this->section === F24Section::Inps) {
            return $this->inps_causal ?: $this->tribute_code;
        }

        return $this->tribute_code ?: $this->inps_causal;
    }

    public function displayLabel(): string
    {
        if (filled($this->label)) {
            return (string) $this->label;
        }

        $code = $this->code();
        if ($code === null || $code === '') {
            return $this->category->label();
        }

        return F24TributeCode::label($code);
    }
}
