<?php

namespace App\Contracts;

/**
 * Contract for customizing the login page behavior.
 *
 * Plugins override this to pre-fill credentials, show hints,
 * or add custom login page elements (e.g., demo mode credentials).
 *
 * Default binding: NullLoginCustomizer (no customization).
 */
interface LoginCustomizer
{
    /**
     * Return credentials to pre-fill on the login form, or null for no pre-fill.
     *
     * @return array{email: string, password: string}|null
     */
    public function credentials(): ?array;

    /**
     * Return a hint message to display above the login form, or null for none.
     */
    public function hint(): ?string;
}
