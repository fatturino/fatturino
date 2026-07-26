<?php

use App\Models\User;

it('renders every migrated settings route as a Livewire page', function (string $route, string $component) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route($route))
        ->assertOk()
        ->assertSeeLivewire($component)
        ->assertDontSee('data-page=', false);
})->with([
    ['settings.company', 'pages::settings.company'],
    ['settings.invoice', 'pages::settings.invoice'],
    ['settings.email', 'pages::settings.email'],
    ['settings.services', 'pages::settings.services'],
    ['settings.advanced', 'pages::settings.advanced'],
    ['settings.openapi', 'pages::settings.openapi'],
]);
