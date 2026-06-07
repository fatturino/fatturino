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
        $fiscalYear = $this->resolveFiscalYear($date);

        $reserved = $sequence->reserveNextNumber($fiscalYear);

        return [
            'fiscal_year' => $fiscalYear,
            'sequential_number' => $reserved['sequential_number'],
            'number' => $reserved['formatted_number'],
        ];
    }

    /**
     * @return array{fiscal_year:int,sequential_number:int,number:string}
     */
    public function resolve(Sequence $sequence, CarbonInterface|string $date, ?string $providedNumber = null): array
    {
        $providedNumber = trim((string) $providedNumber);

        if ($providedNumber === '') {
            return $this->reserve($sequence, $date);
        }

        $fiscalYear = $this->resolveFiscalYear($date);
        $parsedSequentialNumber = $sequence->extractSequentialNumber($providedNumber);

        if ($parsedSequentialNumber !== null) {
            return [
                'fiscal_year' => $fiscalYear,
                'sequential_number' => $parsedSequentialNumber,
                'number' => $providedNumber,
            ];
        }

        $reserved = $this->reserve($sequence, $date);

        return [
            'fiscal_year' => $fiscalYear,
            'sequential_number' => $reserved['sequential_number'],
            'number' => $providedNumber,
        ];
    }

    private function resolveFiscalYear(CarbonInterface|string $date): int
    {
        return $date instanceof CarbonInterface
            ? $date->year
            : now()->parse($date)->year;
    }
}
