<?php

namespace App\Services;

use App\Contracts\LoginCustomizer;

class DemoLoginCustomizer implements LoginCustomizer
{
    public function credentials(): ?array
    {
        return [
            'email' => config('demo.email', 'demo@fatturino.it'),
            'password' => config('demo.password', 'demo'),
        ];
    }

    public function hint(): ?string
    {
        return __('app.demo.hint');
    }
}
