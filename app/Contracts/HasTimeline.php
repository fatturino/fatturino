<?php

namespace App\Contracts;

use OwenIt\Auditing\Contracts\Auditable;

/**
 * Shared contract for models that support the timeline feature
 * (audit log + SDI log merged view).
 */
interface HasTimeline extends Auditable
{
    public function lines();

    public function sdiLogs();
}
