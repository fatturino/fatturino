<?php

use App\Mcp\Servers\FatturinoServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;

$tenantMiddleware = config('cloud.enabled')
    ? [InitializeTenancyByDomain::class]
    : [];

Route::middleware($tenantMiddleware)->group(function (): void {
    Mcp::oauthRoutes();

    Mcp::web('/mcp', FatturinoServer::class)
        ->middleware(['auth:api', 'throttle:mcp']);
});
