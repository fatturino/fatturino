<?php

return [

    // When true, the monitoring UI is hidden and the DSN is read directly from SENTRY_LARAVEL_DSN.
    // fatturino-cloud sets this to true and manages error tracking externally.
    'managed_by_env' => env('MONITORING_MANAGED_BY_ENV', false),

];
