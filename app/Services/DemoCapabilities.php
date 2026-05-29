<?php

namespace App\Services;

use App\Contracts\EnvironmentCapabilities;
use App\Enums\Capability;

class DemoCapabilities implements EnvironmentCapabilities
{
    public function can(Capability|string $action): bool
    {
        return ! $this->cannot($action);
    }

    public function cannot(Capability|string $action): bool
    {
        $actionValue = $action instanceof Capability ? $action->value : $action;

        // Demo mode keeps configuration read-only, but allows daily operations
        // such as creating customers and invoices.
        return in_array($actionValue, [
            Capability::EditCompanySettings->value,
            Capability::EditInvoiceSettings->value,
            Capability::EditSdiSettings->value,
            Capability::EditEmailSettings->value,
            Capability::ManageSequences->value,
            Capability::ManageBackupSettings->value,
        ], true);
    }
}
