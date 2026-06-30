<?php

namespace App\Contracts;

/**
 * Shared contract for models that support the timeline feature
 * (product events + audit log merged view).
 */
interface HasTimeline
{
    public function lines();

    public function events();
}
