<?php

namespace App\Contracts;

/**
 * Shared contract for models that support the timeline feature
 * (audit log + SDI log merged view).
 */
interface HasTimeline
{
    public function lines();

    public function sdiLogs();
}
