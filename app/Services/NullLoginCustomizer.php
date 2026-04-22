<?php

namespace App\Services;

use App\Contracts\LoginCustomizer;

/**
 * Default login customizer: no pre-filled credentials, no hints.
 */
class NullLoginCustomizer implements LoginCustomizer
{
    public function credentials(): ?array
    {
        return null;
    }

    public function hint(): ?string
    {
        return null;
    }
}
