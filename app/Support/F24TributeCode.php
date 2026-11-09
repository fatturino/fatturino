<?php

namespace App\Support;

use App\Enums\F24Category;
use App\Enums\F24Section;

/**
 * Curated F24 tribute / contribution codes from resources/data/f24-tribute-codes.json.
 * Mirrors the AtecoCode pattern: JSON dataset + lookup helpers, not a PHP backed enum.
 */
class F24TributeCode
{
    /** @var array<int, array{code: string, label: string, section: string, category: string, kind?: string}>|null */
    private static ?array $cache = null;

    /**
     * @return array<int, array{code: string, label: string, section: string, category: string, kind?: string}>
     */
    public static function all(): array
    {
        if (self::$cache === null) {
            $path = resource_path('data/f24-tribute-codes.json');

            if (! is_file($path) || ! is_readable($path)) {
                logger()->error('F24 tribute code dataset unavailable', ['path' => $path]);
                self::$cache = [];

                return self::$cache;
            }

            $decoded = json_decode((string) file_get_contents($path), true);
            if (! is_array($decoded)) {
                logger()->error('F24 tribute code dataset is invalid JSON', ['path' => $path]);
                self::$cache = [];

                return self::$cache;
            }

            self::$cache = $decoded;
        }

        return self::$cache;
    }

    /**
     * @return array{code: string, label: string, section: string, category: string, kind?: string}|null
     */
    public static function find(string $code): ?array
    {
        $needle = mb_strtoupper(trim($code));

        foreach (self::all() as $entry) {
            if (mb_strtoupper($entry['code']) === $needle) {
                return $entry;
            }
        }

        return null;
    }

    public static function label(string $code): string
    {
        $entry = self::find($code);

        return $entry !== null ? $entry['code'].' - '.$entry['label'] : $code;
    }

    public static function sectionFor(string $code): F24Section
    {
        $entry = self::find($code);

        return F24Section::tryFrom($entry['section'] ?? '') ?? F24Section::Other;
    }

    public static function categoryFor(string $code): F24Category
    {
        $entry = self::find($code);

        return F24Category::tryFrom($entry['category'] ?? '') ?? F24Category::Other;
    }

    /**
     * @return array<int, array{id: string, name: string, section: string, category: string}>
     */
    public static function options(?string $search = null, ?string $category = null, ?string $regime = null): array
    {
        $entries = self::all();

        if ($category !== null && $category !== '') {
            $entries = array_filter($entries, fn (array $e) => $e['category'] === $category);
        }

        if ($regime === 'RF19') {
            $entries = array_filter(
                $entries,
                fn (array $e) => ! in_array($e['category'], ['iva', 'irpef'], true)
            );
        } elseif ($regime !== null && $regime !== '') {
            $entries = array_filter($entries, fn (array $e) => $e['category'] !== 'sostitutiva');
        }

        if ($search !== null && $search !== '') {
            $needle = mb_strtolower($search);
            $entries = array_filter(
                $entries,
                fn (array $e) => str_starts_with(mb_strtolower($e['code']), $needle)
                    || str_contains(mb_strtolower($e['label']), $needle)
            );
        }

        return array_values(array_map(
            fn (array $e) => [
                'id' => $e['code'],
                'name' => $e['code'].' - '.$e['label'],
                'section' => $e['section'],
                'category' => $e['category'],
            ],
            $entries
        ));
    }

    /** @internal */
    public static function clearCache(): void
    {
        self::$cache = null;
    }
}
