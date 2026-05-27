<?php

return [
    'managed_by_env' => env('OPENAPI_MANAGED_BY_ENV', false),
    'sandbox_url' => env('OPENAPI_SDI_SANDBOX_URL', 'https://test.sdi.openapi.it'),
    'production_url' => env('OPENAPI_SDI_PRODUCTION_URL', 'https://sdi.openapi.it'),
];
