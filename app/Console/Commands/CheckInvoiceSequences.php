<?php

namespace App\Console\Commands;

use App\Models\Sequence;
use Illuminate\Console\Command;
use RuntimeException;

class CheckInvoiceSequences extends Command
{
    protected $signature = 'invoices:check-sequences
                            {--year= : Fiscal year to check (default: current year)}';

    protected $description = 'Detect gaps in invoice numbering sequences for a given fiscal year';

    public function handle(): int
    {
        $year = (int) ($this->option('year') ?? now()->year);
        $hasGaps = false;

        // Purchase sequences are numbered by suppliers, not by us, so skip them
        $sequences = Sequence::query()
            ->where('type', '!=', 'purchase')
            ->get();

        foreach ($sequences as $sequence) {
            $numbers = $sequence->invoices()
                ->where('fiscal_year', $year)
                ->pluck('sequential_number')
                ->filter()
                ->map(fn ($n) => (int) $n)
                ->sort()
                ->values();

            if ($numbers->isEmpty()) {
                continue;
            }

            $expected = range(1, $numbers->max());
            $missing = array_values(array_diff($expected, $numbers->all()));

            if (! empty($missing)) {
                $hasGaps = true;
                $message = sprintf(
                    'Invoice sequence gap: sequence "%s" (type=%s), year %d, missing numbers: %s',
                    $sequence->name,
                    $sequence->type,
                    $year,
                    implode(', ', $missing)
                );

                $this->error($message);
                // Surface to error tracker (Sentry) so self-hosted admins get notified
                report(new RuntimeException($message));

                continue;
            }

            $this->info(sprintf(
                'Sequence "%s" (type=%s), year %d: %d invoices, no gaps',
                $sequence->name,
                $sequence->type,
                $year,
                $numbers->count()
            ));
        }

        return $hasGaps ? self::FAILURE : self::SUCCESS;
    }
}
