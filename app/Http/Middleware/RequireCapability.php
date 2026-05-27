<?php

namespace App\Http\Middleware;

use App\Contracts\EnvironmentCapabilities;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireCapability
{
    public function __construct(
        private readonly EnvironmentCapabilities $capabilities
    ) {}

    public function handle(Request $request, Closure $next, string $capability): Response
    {
        if ($this->capabilities->cannot($capability)) {
            abort(403, 'Operazione non consentita in questa modalità.');
        }

        return $next($request);
    }
}
