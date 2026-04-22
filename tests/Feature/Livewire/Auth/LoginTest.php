<?php

namespace Tests\Feature\Livewire\Auth;

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders()
    {
        User::factory()->create();

        $this->get('/login')
            ->assertSeeLivewire(Login::class)
            ->assertStatus(200);
    }

    public function test_redirects_to_setup_when_no_users_exist()
    {
        Livewire::test(Login::class)
            ->assertRedirect(route('setup'));
    }

    public function test_successful_login_redirects_to_dashboard()
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertRedirect(route('dashboard'));
    }

    public function test_failed_login_shows_error()
    {
        User::factory()->create(['email' => 'user@example.com', 'password' => bcrypt('correct')]);

        Livewire::test(Login::class)
            ->set('email', 'user@example.com')
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertHasErrors(['email']);
    }

    public function test_login_validates_email_required()
    {
        User::factory()->create();

        Livewire::test(Login::class)
            ->set('email', '')
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors(['email']);
    }

    public function test_login_validates_password_required()
    {
        User::factory()->create();

        Livewire::test(Login::class)
            ->set('email', 'user@example.com')
            ->set('password', '')
            ->call('login')
            ->assertHasErrors(['password']);
    }
}
