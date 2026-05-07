<?php

namespace Database\Factories;

use App\Enums\SdiStatus;
use App\Models\Invoice;
use App\Models\SdiLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SdiLog>
 */
class SdiLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'event_type' => 'sent',
            'status' => SdiStatus::Sent,
            'message' => null,
            'raw_payload' => [],
        ];
    }

    public function rejected(): static
    {
        return $this->state([
            'status' => SdiStatus::Rejected,
            'message' => 'Fattura rifiutata per errori formali',
        ]);
    }

    public function delivered(): static
    {
        return $this->state(['status' => SdiStatus::Delivered]);
    }
}
