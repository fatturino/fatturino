<?php

return [
    'enabled' => (bool) env('FATTURINO_DEMO', false),
    'email' => env('DEMO_EMAIL', 'demo@fatturino.it'),
    'password' => env('DEMO_PASSWORD', 'demo'),
    'reset_interval_minutes' => (int) env('DEMO_RESET_INTERVAL_MINUTES', 60),
];
