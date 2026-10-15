<?php

use App\Contracts\EnvironmentCapabilities;
use App\Models\User;
use App\Services\DemoCapabilities;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;

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

it('runs outbound SDI reconciliation from the diagnostics page without sending new documents', function () {
    Artisan::shouldReceive('call')
        ->once()
        ->with('openapi:reconcile', ['--recover-sends-only' => true, '--details' => true])
        ->andReturn(0);
    Artisan::shouldReceive('output')->once()->andReturn('Outbound sends recovered: 1');

    Livewire::test('pages::settings.advanced')
        ->call('reconcileOutboundSends')
        ->assertSet('reconciliationError', null)
        ->assertSee('Outbound sends recovered: 1');
});

it('blocks restricted users from testing email and backup-service connections', function () {
    $user = User::factory()->create();
    app()->instance(EnvironmentCapabilities::class, new DemoCapabilities);

    $this->actingAs($user)
        ->post(route('settings.email.test'))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('settings.services.test'))
        ->assertForbidden();
});
