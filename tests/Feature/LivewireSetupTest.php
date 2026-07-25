<?php

use App\Models\User;
use App\Settings\CompanySettings;
use App\Settings\InvoiceSettings;
use Livewire\Livewire;

it('renders setup as a Livewire page and redirects completed instances to login', function () {
    $this->get(route('setup'))->assertOk()->assertSeeLivewire('pages::setup');

    User::factory()->create();
    $this->get(route('setup'))->assertRedirect(route('login'));
});

it('persists each setup step and completes the setup', function () {
    Livewire::test('pages::setup')
        ->set('name', 'Mario Rossi')
        ->set('email', 'mario@example.test')
        ->set('password', 'password-setup')
        ->set('password_confirmation', 'password-setup')
        ->call('next')
        ->assertSet('step', 2)
        ->set('company_name', 'Rossi SRL')
        ->set('company_vat_number', 'IT01114601006')
        ->set('company_tax_code', 'RSSMRA80A01H501U')
        ->set('company_fiscal_regime', 'RF19')
        ->call('next')
        ->assertSet('step', 3)
        ->set('company_address', 'Via Roma 1')
        ->set('company_postal_code', '20100')
        ->set('company_city', 'Milano')
        ->set('company_province', 'MI')
        ->set('company_country', 'IT')
        ->set('company_pec', 'amministrazione@rossi.test')
        ->set('company_sdi_code', 'ABC1234')
        ->call('next')
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', ['email' => 'mario@example.test', 'is_admin' => true]);
    expect(app(CompanySettings::class)->company_fiscal_regime)->toBe('RF19')
        ->and(app(CompanySettings::class)->rf19_self_invoices_enabled)->toBeFalse()
        ->and(app(InvoiceSettings::class)->withholding_tax_enabled)->toBeFalse()
        ->and(app(InvoiceSettings::class)->auto_stamp_duty)->toBeTrue();
    expect(session()->has('setup_step'))->toBeFalse()->and(session()->has('setup_data'))->toBeFalse();
});

it('validates the current setup step before allowing progress', function () {
    Livewire::test('pages::setup')->call('next')->assertHasErrors(['name', 'email', 'password']);
});
