<?php

use App\Models\User;
use Laravel\Passport\Client;

it('rejects unauthenticated mcp http requests', function () {
    $response = $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
        'params' => (object) [],
    ], [
        'Accept' => 'application/json, text/event-stream',
        'Content-Type' => 'application/json',
        'MCP-Protocol-Version' => '2025-11-25',
    ]);

    $response->assertUnauthorized();
});

it('shows the oauth consent page with the requesting client name for a logged-in user', function () {
    $user = User::factory()->create();
    $client = Client::factory()->asPublic()->create([
        'name' => 'Cursor Agent Demo',
        'redirect_uris' => ['http://127.0.0.1/callback'],
        'grant_types' => ['authorization_code', 'refresh_token'],
    ]);

    $codeVerifier = str_repeat('a', 64);
    $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

    $response = $this->actingAs($user)->get(route('passport.authorizations.authorize', [
        'client_id' => $client->id,
        'redirect_uri' => 'http://127.0.0.1/callback',
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'state' => 'xyz',
        'code_challenge' => $codeChallenge,
        'code_challenge_method' => 'S256',
    ]));

    $response->assertOk();
    $response->assertSee('Cursor Agent Demo');
    $response->assertSee('Chi chiede l’accesso');
    $response->assertSee($user->email);
    $response->assertSee('Approva');
    $response->assertSee('Rifiuta');
});

it('exposes oauth protected resource metadata for mcp discovery', function () {
    $response = $this->getJson('/.well-known/oauth-protected-resource');

    $response->assertOk();
    $response->assertJsonStructure([
        'resource',
        'authorization_servers',
        'scopes_supported',
    ]);
    expect($response->json('scopes_supported'))->toContain('mcp:use');
});
