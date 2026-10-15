<?php

namespace App\Services;

use InvalidArgumentException;

class SdiSubmissionDriverSelector
{
    public function __construct(private readonly SdiSubmissionDriverRegistry $drivers) {}

    public function selectForNewSubmission(): string
    {
        $key = (string) config('sdi.driver');

        // Preserve existing installations that used SDI_PROVIDER=openapi.
        $key = $key === 'openapi' ? 'openapi_legacy' : $key;

        if ($key === '') {
            throw new InvalidArgumentException('The SDI submission driver is not configured.');
        }

        $this->drivers->get($key);

        return $key;
    }
}
