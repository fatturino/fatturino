<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Application ULID generator. Always lowercase (Crockford base32).
 */
final class Ulid
{
    public static function generate(): string
    {
        return strtolower((string) Str::ulid());
    }
}
