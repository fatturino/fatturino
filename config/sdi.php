<?php

return [
    // SDI_DRIVER is the preferred name. SDI_PROVIDER remains a temporary deployment alias.
    'driver' => env('SDI_DRIVER', env('SDI_PROVIDER', 'openapi_legacy')),
];
