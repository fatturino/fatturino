<?php

use App\Models\Contact;
use App\Models\FiscalDocument;
use App\Models\User;
use App\Settings\CompanySettings;
use App\Settings\OpenApiSettings;
use Database\Seeders\OpenApiSandboxSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    Carbon::setTestNow('2026-10-14 10:00:00');
    config()->set('fe-openapi.managed_by_env', false);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('seeds an RF01 OpenAPI sandbox dataset for the current and previous year without changing the token', function () {
    $settings = app(OpenApiSettings::class);
    $settings->api_token = 'existing-sandbox-token';
    $settings->save();

    $this->seed(OpenApiSandboxSeeder::class);

    $company = app(CompanySettings::class);
    app()->forgetInstance(OpenApiSettings::class);
    $openApi = app(OpenApiSettings::class);

    expect($company->company_fiscal_regime)->toBe('RF01')
        ->and($openApi->sandbox)->toBeTrue()
        ->and($openApi->api_token)->toBe('existing-sandbox-token')
        ->and(FiscalDocument::query()->pluck('fiscal_year')->unique()->sort()->values()->all())->toBe([2025, 2026])
        ->and(FiscalDocument::query()->where('status', 'xml_validated')->count())->toBe(1)
        ->and(FiscalDocument::query()->count())->toBe(10)
        ->and(Contact::query()->count())->toBe(4)
        ->and(User::query()->where('email', 'sandbox@fatturino.test')->where('is_admin', true)->count())->toBe(1)
        ->and(Hash::check('sandbox', User::query()->where('email', 'sandbox@fatturino.test')->sole()->password))->toBeTrue();
});

it('replaces the operational sandbox dataset when seeded again', function () {
    $this->seed(OpenApiSandboxSeeder::class);
    $firstDocumentCount = FiscalDocument::query()->count();

    $this->seed(OpenApiSandboxSeeder::class);

    expect(FiscalDocument::query()->count())->toBe($firstDocumentCount)
        ->and(Contact::query()->count())->toBe(4)
        ->and(User::query()->count())->toBe(1)
        ->and(FiscalDocument::query()->whereDoesntHave('contact')->count())->toBe(0);
});

it('refuses to run in production', function () {
    config()->set('app.env', 'production');

    expect(fn () => $this->seed(OpenApiSandboxSeeder::class))
        ->toThrow(RuntimeException::class, 'cannot run in production');
});
