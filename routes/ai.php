<?php

use App\Mcp\Servers\FatturinoServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::oauthRoutes();

Mcp::web('/mcp', FatturinoServer::class)
    ->middleware(['auth:api', 'throttle:mcp']);
