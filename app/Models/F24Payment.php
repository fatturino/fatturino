<?php

namespace App\Models;

use App\Enums\F24PaymentSource;
use App\Enums\F24PaymentStatus;
use App\Support\Ulid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class F24Payment extends Model
{
    use HasFactory;

    protected $table = 'f24_payments';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'source' => F24PaymentSource::class,
            'status' => F24PaymentStatus::class,
            'due_date' => 'date:Y-m-d',
            'paid_at' => 'date:Y-m-d',
            'total_debit_cents' => 'integer',
            'total_credit_cents' => 'integer',
            'balance_cents' => 'integer',
            'ocr_raw' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $payment): void {
            if (empty($payment->public_id)) {
                $payment->public_id = Ulid::generate();
            } else {
                $payment->public_id = strtolower((string) $payment->public_id);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function lines(): HasMany
    {
        return $this->hasMany(F24PaymentLine::class, 'f24_payment_id')->orderBy('position');
    }

    public function recalculateTotals(): void
    {
        $debit = (int) $this->lines()->sum('debit_cents');
        $credit = (int) $this->lines()->sum('credit_cents');

        $this->forceFill([
            'total_debit_cents' => $debit,
            'total_credit_cents' => $credit,
            'balance_cents' => $debit - $credit,
        ])->save();
    }

    public function isOverdue(): bool
    {
        if ($this->status !== F24PaymentStatus::Planned || $this->due_date === null) {
            return false;
        }

        return $this->due_date->lt(now()->startOfDay());
    }

    public function primaryLabel(): string
    {
        $first = $this->lines->first();

        if ($first === null) {
            return __('app.f24.untitled');
        }

        return $first->displayLabel();
    }
}
