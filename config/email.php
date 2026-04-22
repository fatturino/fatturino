<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Email / SMTP managed by environment
    |--------------------------------------------------------------------------
    |
    | When true, SMTP credentials are read exclusively from the environment
    | variables (MAIL_*). The SMTP configuration form is hidden from the UI
    | and DB-stored SMTP settings are never applied at runtime.
    | Email templates are still editable by the user regardless.
    |
    */

    'managed_by_env' => env('SMTP_MANAGED_BY_ENV', false),

];
