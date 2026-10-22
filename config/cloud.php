<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cloud tenancy
    |--------------------------------------------------------------------------
    |
    | Self-hosted is the default deployment mode. The tenancy package is only
    | registered when this explicit flag is enabled, so self-hosted processes
    | never require or attempt to connect to a central database.
    |
    */
    'enabled' => env('FATTURINO_CLOUD', false),
];
