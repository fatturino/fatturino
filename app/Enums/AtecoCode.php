<?php

namespace App\Enums;

/**
 * Provides access to the full ATECO 2025 classification loaded from the JSON resource file.
 *
 * Not a PHP enum: the 3257 entries (six hierarchical levels) make a backed enum impractical.
 * The JSON source is resources/data/ateco-2025.json (ISTAT ATECO 2025 via A35G/Codici-ATECO-2025).
 */
class AtecoCode
{
    // Classificazione levels from the JSON source
    public const LEVEL_SEZIONE       = 'Sezione';
    public const LEVEL_DIVISIONE     = 'Divisione';
    public const LEVEL_GRUPPO        = 'Gruppo';
    public const LEVEL_CLASSE        = 'Classe';
    public const LEVEL_CATEGORIA     = 'Categoria';
    public const LEVEL_SOTTOCATEGORIA = 'Sottocategoria';

    /** @var array<int, array{codice: string, titolo: string, classificazione: string}>|null */
    private static ?array $cache = null;

    /**
     * Returns all ATECO 2025 entries from the JSON resource.
     *
     * @return array<int, array{codice: string, titolo: string, classificazione: string}>
     */
    public static function all(): array
    {
        if (self::$cache === null) {
            $path = resource_path('data/ateco-2025.json');
            self::$cache = json_decode(file_get_contents($path), true);
        }

        return self::$cache;
    }

    /**
     * Returns all entries filtered by classificazione level.
     *
     * @return array<int, array{codice: string, titolo: string, classificazione: string}>
     */
    public static function byLevel(string $level): array
    {
        return array_values(
            array_filter(self::all(), fn (array $e) => $e['classificazione'] === $level)
        );
    }

    /**
     * Finds a single entry by its codice, or null if not found.
     *
     * @return array{codice: string, titolo: string, classificazione: string}|null
     */
    public static function find(string $codice): ?array
    {
        foreach (self::all() as $entry) {
            if ($entry['codice'] === $codice) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Returns options for multi-select dropdowns, optionally filtered by search string.
     * Each option has 'id' (codice) and 'name' (codice + titolo).
     *
     * @param  string|null  $search  Case-insensitive substring filter applied to codice and titolo
     * @param  string|null  $level   Optional classificazione level filter
     * @return array<int, array{id: string, name: string}>
     */
    public static function options(?string $search = null, ?string $level = null): array
    {
        $entries = self::all();

        if ($level !== null) {
            $entries = array_filter($entries, fn (array $e) => $e['classificazione'] === $level);
        }

        if ($search !== null && $search !== '') {
            $needle = mb_strtolower($search);
            $entries = array_filter(
                $entries,
                fn (array $e) => str_starts_with(mb_strtolower($e['codice']), $needle)
                    || str_contains(mb_strtolower($e['titolo']), $needle)
            );
        }

        return array_values(array_map(
            fn (array $e) => ['id' => $e['codice'], 'name' => $e['codice'] . ' - ' . $e['titolo']],
            $entries
        ));
    }

    /**
     * Returns a human-readable label for a stored codice value, or the raw codice if unknown.
     */
    public static function label(string $codice): string
    {
        $entry = self::find($codice);

        return $entry !== null ? $entry['codice'] . ' - ' . $entry['titolo'] : $codice;
    }
}
