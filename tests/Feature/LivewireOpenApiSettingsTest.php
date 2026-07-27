<?php

use App\Actions\ManageOpenApiSettings;
use App\Contracts\EnvironmentCapabilities;
use App\Models\User;
use App\Services\DemoCapabilities;
use App\Services\UnrestrictedCapabilities;
use App\Settings\CompanySettings;
use App\Settings\OpenApiSettings;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    app()->instance(EnvironmentCapabilities::class, new UnrestrictedCapabilities);

    $company = app(CompanySettings::class);
    $company->company_vat_number = 'IT12345678901';
    $company->company_email = 'azienda@example.test';
    $company->conservation_acknowledged = false;
    $company->save();
});

it('initializes the Livewire state without exposing the persisted API token', function () {
    $settings = app(OpenApiSettings::class);
    $settings->api_token = 'token-that-must-not-reach-the-browser';
    $settings->sandbox = true;
    $settings->company_sdi_code = 'ABCDEFG';
    $settings->webhook_url = 'https://webhook.example.test';
    $settings->save();

    Livewire::test('pages::settings.openapi')
        ->assertSet('apiToken', '')
        ->assertSet('sandbox', true)
        ->assertSet('companySdiCode', 'ABCDEFG')
        ->assertSet('webhookUrl', 'https://webhook.example.test')
        ->assertSee('Simulazione sandbox')
        ->assertDontSee('token-that-must-not-reach-the-browser');
});

it('shows the sandbox section reactively when sandbox is enabled', function () {
    $settings = app(OpenApiSettings::class);
    $settings->sandbox = false;
    $settings->save();

    Livewire::test('pages::settings.openapi')
        ->assertDontSee('Simulazione sandbox')
        ->set('sandbox', true)
        ->assertSee('Simulazione sandbox');
});

it('saves non-secret settings while preserving a blank API token', function () {
    $settings = app(OpenApiSettings::class);
    $settings->api_token = 'existing-token';
    $settings->sandbox = false;
    $settings->save();

    Livewire::test('pages::settings.openapi')
        ->set('sandbox', true)
        ->set('companySdiCode', 'ABCDEFG')
        ->set('webhookUrl', 'https://callback.example.test')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('apiToken', '')
        ->assertSee('Impostazioni OpenAPI salvate.');

    $fresh = app(OpenApiSettings::class);
    expect($fresh->api_token)->toBe('existing-token')
        ->and($fresh->sandbox)->toBeTrue()
        ->and($fresh->company_sdi_code)->toBe('ABCDEFG')
        ->and($fresh->webhook_url)->toBe('https://callback.example.test');
});

it('checks the current unsaved configuration without persisting it', function () {
    $settings = app(OpenApiSettings::class);
    $settings->api_token = '';
    $settings->sandbox = false;
    $settings->save();

    Http::fake([
        'https://test.sdi.openapi.it/business_registry_configurations/*' => Http::response([], 404),
    ]);

    Livewire::test('pages::settings.openapi')
        ->set('apiToken', 'temporary-token')
        ->set('sandbox', true)
        ->call('checkConnection')
        ->assertHasNoErrors()
        ->assertSee('Connessione riuscita, ma il servizio non è attivo per questa Partita IVA.');

    expect(app(OpenApiSettings::class)->api_token)->toBe('')
        ->and(app(OpenApiSettings::class)->sandbox)->toBeFalse();

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://test.sdi.openapi.it/'));
});

it('keeps environment-managed settings immutable from the Livewire payload', function () {
    config()->set('fe-openapi.managed_by_env', true);
    config()->set('fe-openapi.api_token', 'env-token-123');
    config()->set('fe-openapi.sandbox', true);
    config()->set('fe-openapi.company_sdi_code', 'ENVSDI1');
    app()->forgetInstance(OpenApiSettings::class);

    $settings = app(OpenApiSettings::class);
    $settings->api_token = 'database-token';
    $settings->sandbox = false;
    $settings->company_sdi_code = 'AAAAAAA';

    Livewire::test('pages::settings.openapi')
        ->assertSet('apiToken', '')
        ->assertSet('sandbox', true)
        ->assertSet('companySdiCode', 'ENVSDI1')
        ->set('apiToken', 'request-token')
        ->set('sandbox', false)
        ->call('save')
        ->assertSee('Impostazioni OpenAPI salvate.');

    $fresh = app(OpenApiSettings::class);
    expect($fresh->api_token)->toBe('database-token')
        ->and($fresh->sandbox)->toBeFalse()
        ->and($fresh->company_sdi_code)->toBe('AAAAAAA');
});

it('acknowledges conservation without requiring a full page reload', function () {
    Livewire::test('pages::settings.openapi')
        ->call('acknowledgeConservation')
        ->assertSet('conservationAcknowledged', true)
        ->assertSee('Obbligo di conservazione preso in carico.');

    expect(app(CompanySettings::class)->conservation_acknowledged)->toBeTrue();
});

it('enforces the SDI settings capability for every mutable provider action', function () {
    app()->instance(EnvironmentCapabilities::class, new DemoCapabilities);

    $component = Livewire::test('pages::settings.openapi');

    expect(fn () => $component->instance()->save(app(ManageOpenApiSettings::class), app(OpenApiSettings::class)))->toThrow(HttpException::class);
});

it('removes the legacy fetch implementation and JSON UI endpoints', function () {
    $page = file_get_contents(resource_path('views/pages/settings/openapi.blade.php'));

    expect($page)->not->toContain('fetch(')
        ->and($page)->not->toContain('window.location.reload')
        ->and($page)->toContain('wire:loading.attr="disabled"');

    $this->postJson('/api/v1/openapi/save')->assertNotFound();
    $this->postJson('/api/v1/openapi/activate')->assertNotFound();
});
