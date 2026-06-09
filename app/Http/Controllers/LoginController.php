<?php

namespace App\Http\Controllers;

use App\Contracts\LoginCustomizer;
use App\Models\User;
use App\Services\PostHogTelemetryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function show(LoginCustomizer $customizer): RedirectResponse|Response
    {
        // No users yet — redirect to first-time setup
        if (User::count() === 0) {
            return redirect()->route('setup');
        }

        $credentials = $customizer->credentials();

        return Inertia::render('Guest/Login', [
            'appName' => config('app.name'),
            'prefillEmail' => $credentials['email'] ?? '',
            'prefillPassword' => $credentials['password'] ?? '',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $throttleKey = Str::transliterate(Str::lower($request->email).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors([
                'email' => __('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }

        if (! Auth::attempt(
            $request->only('email', 'password'),
            $request->boolean('remember'),
        )) {
            RateLimiter::hit($throttleKey);

            return back()->withErrors([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($throttleKey);
        session()->regenerate();
        app(PostHogTelemetryService::class)->capture('user_logged_in', [], $request->user());

        return redirect()->route('dashboard');
    }
}
