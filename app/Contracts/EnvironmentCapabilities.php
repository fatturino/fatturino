<?php

namespace App\Contracts;

use App\Enums\Capability;

/**
 * Contract for environment-specific capability checks.
 *
 * Plugins override this to restrict actions in specific environments
 * (e.g., demo mode blocks editing, cloud mode limits certain features).
 *
 * Default binding: UnrestrictedCapabilities (everything allowed).
 */
interface EnvironmentCapabilities
{
    /**
     * Check if the given action is allowed in the current environment.
     */
    public function can(Capability|string $action): bool;

    /**
     * Check if the given action is blocked in the current environment.
     */
    public function cannot(Capability|string $action): bool;
}
