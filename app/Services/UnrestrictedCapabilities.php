<?php

namespace App\Services;

use App\Contracts\EnvironmentCapabilities;
use App\Enums\Capability;

/**
 * Default capabilities: everything is allowed.
 *
 * Used in self-hosted and production environments where
 * no plugin overrides the capability contract.
 */
class UnrestrictedCapabilities implements EnvironmentCapabilities
{
    public function can(Capability|string $action): bool
    {
        return true;
    }

    public function cannot(Capability|string $action): bool
    {
        return false;
    }
}
