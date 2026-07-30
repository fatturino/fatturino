<?php

namespace App\Services;

use App\Models\Sequence;
use App\Settings\InvoiceSettings;
use Illuminate\Validation\ValidationException;

class DocumentSequenceResolver
{
    /** @var array<string, string> */
    private const SETTINGS_KEYS = [
        'sales' => 'default_sequence_sales',
        'self_invoice' => 'default_sequence_self_invoice',
        'proforma' => 'default_sequence_proforma',
        'credit_note' => 'default_sequence_credit_notes',
    ];

    public function resolve(string $type): Sequence
    {
        $key = self::SETTINGS_KEYS[$type] ?? null;

        if ($key === null) {
            throw ValidationException::withMessages(['sequence_id' => 'Tipo di documento non supportato.']);
        }

        $sequenceId = app(InvoiceSettings::class)->{$key};
        $sequence = Sequence::query()
            ->whereKey($sequenceId)
            ->where('type', $type)
            ->first();

        $sequence ??= Sequence::query()->where('type', $type)->orderByDesc('is_system')->orderBy('id')->first();

        if ($sequence === null) {
            throw ValidationException::withMessages([
                'invoice' => 'Crea o configura una sequenza per questo documento nelle impostazioni.',
            ]);
        }

        return $sequence;
    }

    public function settingKey(string $type): ?string
    {
        return self::SETTINGS_KEYS[$type] ?? null;
    }

    public function isDefault(Sequence $sequence): bool
    {
        return $this->settingKey($sequence->type) !== null
            && $this->resolve($sequence->type)->is($sequence);
    }

    /** @return array<string, string> */
    public function types(): array
    {
        return self::SETTINGS_KEYS;
    }
}
