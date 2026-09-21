<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('ateco search requires sanctum authentication', function () {
    $this->getJson('/api/v1/ateco/search?q=62')->assertUnauthorized();
});

test('ateco search requires at least two characters', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/ateco/search?q=6')
        ->assertOk()
        ->assertExactJson([]);
});

test('ateco search returns no more than fifty matching codes', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson('/api/v1/ateco/search?q=62')
        ->assertOk()
        ->assertJsonStructure(['*' => ['code', 'description']]);

    expect(count($response->json()))->toBeLessThanOrEqual(50);
});
