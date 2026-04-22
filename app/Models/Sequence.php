<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Sequence extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $attributes = [
        'pattern' => '{SEQ}',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function invoices(): HasMany
    {
        // Use withoutGlobalScopes so the counter works correctly for all invoice types
        // (sales, purchase, self_invoice) — Invoice model has a 'sales' global scope by default
        return $this->hasMany(Invoice::class)->withoutGlobalScopes();
    }

    /**
     * Get the next sequential number for a given year.
     * Each year restarts from 1 independently per sequence.
     */
    public function getNextNumber(?int $year = null): int
    {
        $year = $year ?? now()->year;

        $maxNumber = $this->invoices()
            ->whereYear('date', $year)
            ->max('sequential_number');

        return $maxNumber ? $maxNumber + 1 : 1;
    }

    /**
     * Build the formatted invoice number from the sequence pattern.
     * E.g. pattern "{SEQ}-{ANNO}-AF" with seq=1, year=2026 → "0001-2026-AF"
     */
    public function getFormattedNumber(?int $year = null): string
    {
        $year = $year ?? now()->year;
        $number = $this->getNextNumber($year);
        $pattern = $this->pattern ?? '{SEQ}';

        // Use zero-padding (4 digits) only when the pattern contains more than just {SEQ}
        $needsPadding = $pattern !== '{SEQ}';
        $formattedNumber = $needsPadding
            ? str_pad($number, 4, '0', STR_PAD_LEFT)
            : (string) $number;

        return str_replace(
            ['{SEQ}', '{ANNO}'],
            [$formattedNumber, $year],
            $pattern
        );
    }

    /**
     * Atomically reserve the next sequential number within a transaction.
     * Locks the sequence row to prevent race conditions with concurrent saves.
     *
     * @return array{sequential_number: int, formatted_number: string}
     */
    public function reserveNextNumber(int $year): array
    {
        return DB::transaction(function () use ($year) {
            // Lock this sequence row to serialize concurrent invoice creation
            Sequence::where('id', $this->id)->lockForUpdate()->first();

            $nextNumber = $this->getNextNumber($year);
            $pattern = $this->pattern ?? '{SEQ}';
            $needsPadding = $pattern !== '{SEQ}';
            $formattedNumber = $needsPadding
                ? str_pad($nextNumber, 4, '0', STR_PAD_LEFT)
                : (string) $nextNumber;

            $formatted = str_replace(
                ['{SEQ}', '{ANNO}'],
                [$formattedNumber, $year],
                $pattern
            );

            return [
                'sequential_number' => $nextNumber,
                'formatted_number'  => $formatted,
            ];
        });
    }

    /**
     * Boot the model - add protection hooks
     */
    protected static function booted(): void
    {
        // Prevent deletion of system sequences
        static::deleting(function (Sequence $sequence) {
            // Block 1: System records cannot be deleted
            if ($sequence->is_system) {
                throw new \Exception(
                    'Impossibile eliminare un sezionale di sistema. ' .
                    'I sezionali predefiniti non possono essere rimossi.'
                );
            }

            // Block 2: Sequences in use cannot be deleted
            if ($sequence->invoices()->exists()) {
                $count = $sequence->invoices()->count();
                throw new \Exception(
                    "Impossibile eliminare il sezionale \"{$sequence->name}\". " .
                    "È utilizzato in {$count} fatture. " .
                    'Rimuovere prima i riferimenti per procedere.'
                );
            }
        });

        // Prevent modification of 'type' for system sequences
        static::updating(function (Sequence $sequence) {
            if ($sequence->is_system && $sequence->isDirty('type')) {
                throw new \Exception(
                    'Impossibile modificare il tipo di un sezionale di sistema.'
                );
            }
        });
    }
}
