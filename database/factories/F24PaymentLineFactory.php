<?php

namespace Database\Factories;

use App\Enums\F24Category;
use App\Enums\F24Section;
use App\Models\F24Payment;
use App\Models\F24PaymentLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<F24PaymentLine>
 */
class F24PaymentLineFactory extends Factory
{
    protected $model = F24PaymentLine::class;

    public function definition(): array
    {
        return [
            'f24_payment_id' => F24Payment::factory(),
            'position' => 0,
            'section' => F24Section::Erario,
            'category' => F24Category::Irpef,
            'tribute_code' => '4001',
            'reference_year' => (string) now()->year,
            'installment_or_month' => '0101',
            'inps_causal' => null,
            'inps_period_from' => null,
            'inps_period_to' => null,
            'debit_cents' => 50000,
            'credit_cents' => 0,
            'label' => 'IRPEF - saldo',
        ];
    }
}
