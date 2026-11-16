<?php

use App\Mcp\Servers\FatturinoServer;
use App\Mcp\Tools\SearchContactsTool;
use App\Models\Contact;
use App\Models\User;

it('searches contacts for authenticated mcp users', function () {
    $user = User::factory()->create();
    Contact::factory()->create(['name' => 'Acme Forniture', 'vat_number' => '12345678901']);
    Contact::factory()->create(['name' => 'Altro Cliente', 'vat_number' => '99999999999']);

    FatturinoServer::actingAs($user)->tool(SearchContactsTool::class, [
        'query' => 'Acme',
    ])
        ->assertOk()
        ->assertSee('Acme Forniture')
        ->assertDontSee('Altro Cliente');
});

it('requires authentication for search_contacts', function () {
    Contact::factory()->create(['name' => 'Acme Forniture']);

    FatturinoServer::tool(SearchContactsTool::class, [
        'query' => 'Acme',
    ])->assertHasErrors(['Authentication required.']);
});
