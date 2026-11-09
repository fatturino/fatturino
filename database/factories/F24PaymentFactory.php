<?php

namespace Database\Factories;

use App\Enums\F24Category;
use App\Enums\F24PaymentSource;
use App\Enums\F24PaymentStatus;
use App\Enums\F24Section;
use App\Models\F24Payment;
use App\Models\F24PaymentLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<F24Payment>
 */
class F24PaymentFactory extends Factory
{
    protected $model = F24Payment::class;

    public function definition(): array
    {
        return [
            'source' => F24PaymentSource::Manual,
            'status' => F24PaymentStatus::Planned,
            'due_date' => now()->addDays(10)->toDateString(),
            'paid_at' => null,
            'total_debit_cents' => 0,
            'total_credit_cents' => 0,
            'balance_cents' => 0,
            'protocol_number' => null,
            'notes' => null,
            'source_file_path' => null,
            'source_file_name' => null,
            'source_file_mime' => null,
            'ocr_raw' => null,
        ];
    }

    public function paid(?string $paidAt = null): static
    {
        return $this->state(fn () => [
            'status' => F24PaymentStatus::Paid,
            'paid_at' => $paidAt ?? now()->toDateString(),
            'due_date' => $paidAt ?? now()->toDateString(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => F24PaymentStatus::Draft,
        ]);
    }

    public function withIrpefLine(int $debitCents = 100000): static
    {
        return $this->afterCreating(function (F24Payment $payment) use ($debitCents) {
            F24PaymentLine::factory()->create([
                'f24_payment_id' => $payment->id,
                'position' => 0,
                'section' => F24Section::Erario,
                'category' => F24Category::Irpef,
                'tribute_code' => '4001',
                'reference_year' => (string) (now()->year - 1),
                'installment_or_month' => '0101',
                'debit_cents' => $debitCents,
                'label' => 'IRPEF - saldo',
            ]);
            $payment->recalculateTotals();
        });
    }
}
