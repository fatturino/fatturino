<?php

namespace App\Livewire\Auth;

use App\Contracts\LoginCustomizer;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.guest')]
class Login extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required')]
    public string $password = '';

    public bool $remember = false;

    public function mount(LoginCustomizer $customizer): void
    {
        // No users yet — redirect to first-time setup
        if (User::count() === 0) {
            $this->redirectRoute('setup');

            return;
        }

        // Allow plugins to pre-fill credentials (e.g., demo mode)
        if ($credentials = $customizer->credentials()) {
            $this->email = $credentials['email'];
            $this->password = $credentials['password'];
        }
    }

    public function login(): void
    {
        $this->validate();

        $throttleKey = Str::transliterate(Str::lower($this->email).'|'.request()->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->addError('email', __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]));

            return;
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($throttleKey);
            $this->addError('email', __('auth.failed'));

            return;
        }

        RateLimiter::clear($throttleKey);
        session()->regenerate();

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.auth.login');
    }
}
