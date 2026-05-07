<?php

namespace Tests\Feature\Livewire\Settings;

use App\Contracts\EnvironmentCapabilities;
use App\Enums\Capability;
use App\Livewire\Settings\Email;
use App\Models\User;
use App\Settings\EmailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmailSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_renders_for_authenticated_user()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/email-settings')
            ->assertSeeLivewire(Email::class)
            ->assertStatus(200);
    }

    public function test_saves_smtp_settings()
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Email::class)
            ->set('smtp_host', 'smtp.example.com')
            ->set('smtp_port', 587)
            ->set('smtp_encryption', 'tls')
            ->set('from_address', 'test@example.com')
            ->call('save')
            ->assertHasNoErrors();

        $settings = app(EmailSettings::class);
        $this->assertEquals('smtp.example.com', $settings->smtp_host);
        $this->assertEquals(587, $settings->smtp_port);
        $this->assertEquals('tls', $settings->smtp_encryption);
        $this->assertEquals('test@example.com', $settings->from_address);
    }

    public function test_validates_from_address_as_email()
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Email::class)
            ->set('from_address', 'not-an-email')
            ->call('save')
            ->assertHasErrors(['from_address']);
    }

    public function test_validates_smtp_port_as_integer()
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Email::class)
            ->set('smtp_port', 99999)
            ->call('save')
            ->assertHasErrors(['smtp_port']);
    }

    public function test_mounts_as_readonly_when_capabilities_restrict_editing()
    {
        $user = User::factory()->create();

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
            ->test(Email::class)
            ->assertSet('readonly', true);
    }

    public function test_saves_email_templates()
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Email::class)
            ->set('template_sales_subject', 'Fattura {NUMERO_DOCUMENTO}')
            ->set('template_sales_body', 'Gentile {CLIENTE}')
            ->call('save')
            ->assertHasNoErrors();

        $settings = app(EmailSettings::class);
        $this->assertEquals('Fattura {NUMERO_DOCUMENTO}', $settings->template_sales_subject);
    }
}
