<?php

return [
    'managed_by_env' => env('OPENAPI_MANAGED_BY_ENV', false),
    'sandbox_url' => env('OPENAPI_SDI_SANDBOX_URL', 'https://test.sdi.openapi.it'),
    'production_url' => env('OPENAPI_SDI_PRODUCTION_URL', 'https://sdi.openapi.it'),
    'api_token' => env('OPENAPI_SDI_API_TOKEN', ''),
    'sandbox' => env('OPENAPI_SDI_SANDBOX', false),
    'company_sdi_code' => env('OPENAPI_SDI_COMPANY_SDI_CODE', ''),
    'webhook_url' => env('OPENAPI_SDI_WEBHOOK_URL', ''),
    'webhook_max_body_bytes' => (int) env('OPENAPI_SDI_WEBHOOK_MAX_BODY_BYTES', 262144),
    'webhook_max_requests_per_minute' => (int) env('OPENAPI_SDI_WEBHOOK_MAX_REQUESTS_PER_MINUTE', 120),
    'webhook_payload_retention_days' => (int) env('OPENAPI_SDI_WEBHOOK_PAYLOAD_RETENTION_DAYS', 7),
];
