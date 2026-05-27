<?php

namespace Database\Factories;

use App\Enums\SdiStatus;
use App\Models\EiOutboundLog;
use App\Models\FiscalDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EiOutboundLog>
 */
class EiOutboundLogFactory extends Factory
{
    protected $model = EiOutboundLog::class;

    public function definition(): array
    {
        return [
            'fiscal_document_id' => FiscalDocument::factory(),
            'source_uuid' => fake()->uuid(),
            'event_type' => 'sent',
            'status' => SdiStatus::Sent,
            'message' => null,
            'business_fingerprint' => hash('sha256', fake()->uuid()),
            'raw_payload' => [],
        ];
    }
}
