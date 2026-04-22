<?php

use App\Models\User;
use Fatturino\FeOpenApi\Settings\OpenApiSettings;
use Livewire\Livewire;
use Fatturino\FeOpenApi\Livewire\OpenApiSettingsPage;

beforeEach(function () {
    $settings = app(OpenApiSettings::class);
    $settings->api_token = 'existing_token';
    $settings->sandbox = true;
    $settings->activated = true;
    $settings->company_sdi_code = '';
    $settings->webhook_url = 'https://old.example.com';
    $settings->webhook_secret = 'existing_secret';
    $settings->save();
});

it('does not save any fields when service is active', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(OpenApiSettingsPage::class)
        ->set('webhook_url', 'https://new.example.com')
        ->set('api_token', 'new_token_attempt')
        ->call('save')
        ->assertHasNoErrors();

    $saved = app(OpenApiSettings::class);
    expect($saved->api_token)->toBe('existing_token');
    expect($saved->webhook_url)->toBe('https://old.example.com');
});

it('does not save api_token when service is active', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(OpenApiSettingsPage::class)
        ->set('api_token', 'new_token_attempt')
        ->call('save')
        ->assertHasNoErrors();

    // Token should remain unchanged because activated blocks all edits
    $saved = app(OpenApiSettings::class);
    expect($saved->api_token)->toBe('existing_token');
});
