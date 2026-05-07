<?php

namespace Tests\Feature\Livewire\Settings;

use App\Contracts\EnvironmentCapabilities;
use App\Enums\Capability;
use App\Livewire\Settings\Company;
use App\Models\User;
use App\Settings\CompanySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CompanySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_renders_for_authenticated_user()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/company-settings')
            ->assertSeeLivewire(Company::class)
            ->assertStatus(200);
    }

    public function test_saves_updated_company_settings()
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Company::class)
            ->set('company_name', 'New Company Name')
            ->call('save')
            ->assertHasNoErrors();

        $settings = app(CompanySettings::class);
        $this->assertEquals('New Company Name', $settings->company_name);
    }

    public function test_validates_company_name_required()
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Company::class)
            ->set('company_name', '')
            ->call('save')
            ->assertHasErrors(['company_name']);
    }

    public function test_mounts_as_readonly_when_capabilities_restrict_editing()
    {
        $user = User::factory()->create();

        // Bind a restrictive capabilities implementation
        $this->app->bind(EnvironmentCapabilities::class, function () {
            return new class implements EnvironmentCapabilities
            {
                public function can(Capability|string $capability): bool
                {
                    return false;
                }

                public function cannot(Capability|string $capability): bool
                {
                    return true;
                }
            };
        });

        Livewire::actingAs($user)
            ->test(Company::class)
            ->assertSet('readonly', true);
    }
}
