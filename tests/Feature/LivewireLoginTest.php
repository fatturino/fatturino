<?php

use App\Models\User;
use App\Services\DemoLoginCustomizer;
use Livewire\Livewire;

it('redirects guests to setup when no user exists', function () {
    $this->get(route('login'))->assertRedirect(route('setup'));
});

it('renders the login as a Livewire page', function () {
    User::factory()->create();

    $this->get(route('login'))
        ->assertOk()
        ->assertSeeLivewire('pages::auth.login')
        ->assertDontSee('data-page=', false);
});

it('prefills credentials when demo mode is enabled', function () {
    config()->set('demo.enabled', true);
    config()->set('demo.email', 'demo@example.test');
    config()->set('demo.password', 'demo-password');
    app()->instance(\App\Contracts\LoginCustomizer::class, new DemoLoginCustomizer);

    User::factory()->create();

    Livewire::test('pages::auth.login')
        ->assertSet('email', 'demo@example.test')
        ->assertSet('password', 'demo-password');
});

it('authenticates valid credentials and redirects to the dashboard', function () {
    $user = User::factory()->create(['password' => 'secret-password']);

    Livewire::test('pages::auth.login')
        ->set('email', $user->email)
        ->set('password', 'secret-password')
        ->call('authenticate')
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});
