<?php

namespace Database\Seeders;

use App\Models\Sequence;
use Illuminate\Database\Seeder;

class SequenceSeeder extends Seeder
{
    /**
     * Seed the five standard invoice sequences.
     * Safe for production — creates essential numbering sequences.
     * All sequences are marked is_system = true to protect from deletion.
     */
    public function run(): void
    {
        $sequences = [
            [
                'name'      => 'Fatture Elettroniche',
                'pattern'   => '{SEQ}',
                'type'      => 'electronic_invoice',
                'is_system' => true,
            ],
            [
                'name'      => 'Acquisti',
                'pattern'   => 'ACQ-{SEQ}',
                'type'      => 'purchase',
                'is_system' => true,
            ],
            [
                'name'      => 'Autofatture',
                'pattern'   => 'AUTO-{SEQ}',
                'type'      => 'self_invoice',
                'is_system' => true,
            ],
            [
                'name'      => 'ProForma',
                'pattern'   => 'PRO-{SEQ}',
                'type'      => 'proforma',
                'is_system' => true,
            ],
            [
                'name'      => 'Note di Credito',
                'pattern'   => 'NC-{SEQ}',
                'type'      => 'credit_note',
                'is_system' => true,
            ],
            [
                'name'      => 'Preventivi',
                'pattern'   => 'PREV-{SEQ}',
                'type'      => 'quote',
                'is_system' => true,
            ],
        ];

        foreach ($sequences as $sequence) {
            Sequence::updateOrCreate(
                ['name' => $sequence['name']], // Unique key
                $sequence
            );
        }
    }
}
