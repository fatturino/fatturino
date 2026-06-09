<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('authenticated pages share telemetry context and user id for posthog bootstrap', function () {
    config()->set('app.instance_id', 'tenant-alpha');
    config()->set('services.posthog.frontend_key', 'phc_test_frontend');
    config()->set('services.posthog.frontend_host', 'https://h.fatturino.test');
    config()->set('services.posthog.ui_host', 'https://eu.posthog.com');

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
            ->where('telemetry.posthog.key', 'phc_test_frontend')
            ->where('telemetry.posthog.apiHost', 'https://h.fatturino.test')
            ->where('telemetry.posthog.uiHost', 'https://eu.posthog.com')
        );
});
