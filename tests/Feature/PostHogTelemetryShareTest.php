<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('authenticated pages share telemetry context and user id for posthog bootstrap', function () {
    config()->set('app.instance_id', 'tenant-alpha');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('auth.user.id', $user->id)
            ->where('telemetry.instanceKey', 'tenant-alpha')
            ->where('telemetry.appName', config('app.name'))
            ->where('telemetry.appEnv', config('app.env'))
            ->where('telemetry.appVersion', config('app.version'))
        );
});
