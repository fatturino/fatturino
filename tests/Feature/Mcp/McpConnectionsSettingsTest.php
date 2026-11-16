<?php

use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Livewire\Livewire;

it('lists and revokes mcp oauth tokens from settings', function () {
    $user = User::factory()->create();
    $client = Client::factory()->asPublic()->create([
        'name' => 'Claude Desktop',
        'redirect_uris' => ['http://127.0.0.1/callback'],
    ]);

    $token = $user->tokens()->create([
        'id' => Str::random(40),
        'client_id' => $client->id,
        'name' => 'Claude Desktop',
        'scopes' => ['mcp:use'],
        'revoked' => false,
        'expires_at' => now()->addDay(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::settings.mcp')
        ->assertSee('Claude Desktop')
        ->assertSee('mcp:use')
        ->call('revoke', $token->id)
        ->assertSee('Nessuna connessione MCP attiva');

    expect($token->fresh()->revoked)->toBeTrue();
});
