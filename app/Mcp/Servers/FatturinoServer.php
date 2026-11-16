<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateSelfInvoiceTool;
use App\Mcp\Tools\SearchContactsTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;

#[Name('Fatturino')]
#[Version('1.0.0')]
#[Instructions('Create draft self-invoices (autofatture) for this Fatturino instance. Never send documents to SDI. Search contacts first to resolve contact_id.')]
class FatturinoServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        SearchContactsTool::class,
        CreateSelfInvoiceTool::class,
    ];

    /**
     * @var array<int, class-string<Server\Resource>>
     */
    protected array $resources = [];

    /**
     * @var array<int, class-string<Prompt>>
     */
    protected array $prompts = [];
}
