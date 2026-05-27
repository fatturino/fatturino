<?php

namespace App\Services\Domain;

use App\Models\Sequence;
use Carbon\CarbonInterface;

class DocumentNumberingService
{
    /**
     * @return array{fiscal_year:int,sequential_number:int,number:string}
     */
    public function reserve(Sequence $sequence, CarbonInterface|string $date): array
    {
        $fiscalYear = $date instanceof CarbonInterface
            ? $date->year
            : now()->parse($date)->year;

        $reserved = $sequence->reserveNextNumber($fiscalYear);

        return [
            'fiscal_year' => $fiscalYear,
            'sequential_number' => $reserved['sequential_number'],
            'number' => $reserved['formatted_number'],
        ];
    }
}
