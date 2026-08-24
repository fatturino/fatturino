<?php

use App\Contracts\LoginCustomizer;
use App\Models\User;
use App\Services\PostHogTelemetryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::guest')] #[Title('Fatturino - Accedi')] class extends Component {
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function mount(LoginCustomizer $customizer): void
    {
        if (User::query()->doesntExist()) {
            $this->redirectRoute('setup', navigate: false);

            return;
        }
        $credentials = $customizer->credentials() ?? [];
        $this->email = (string) ($credentials['email'] ?? '');
        $this->password = (string) ($credentials['password'] ?? '');
    }

    public function authenticate(PostHogTelemetryService $telemetry): void
    {
        $credentials = $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);
        $throttleKey = Str::transliterate(Str::lower($credentials['email']).'|'.request()->ip());
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $this->addError('email', __('auth.throttle', [
                'seconds' => RateLimiter::availableIn($throttleKey),
                'minutes' => ceil(RateLimiter::availableIn($throttleKey) / 60),
            ]));

            return;
        }
        if (! Auth::attempt($credentials, $this->remember)) {
            RateLimiter::hit($throttleKey);
            $this->addError('email', __('auth.failed'));

            return;
        }
        RateLimiter::clear($throttleKey);
        session()->regenerate();
        $telemetry->capture('user_logged_in', [], request()->user());
        $this->redirectRoute('dashboard', navigate: false);
    }
};
?>

<main class="flex min-h-dvh items-center justify-center bg-[linear-gradient(135deg,var(--color-app-background),white)] p-4 sm:p-6 lg:p-8">
    <div class="grid w-full max-w-5xl overflow-hidden rounded-xl border border-border bg-white shadow-[var(--shadow-elevated)] md:grid-cols-2">
        <section class="order-2 px-6 py-8 sm:p-10 md:order-1 lg:p-14">
            <img src="{{ asset('brand/logo-dark.svg') }}" alt="Fatturino" class="h-9 w-auto">

            <header class="mt-9 max-w-md">
                <p class="text-sm font-medium text-primary">Bentornato</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-content">Accedi al tuo account</h1>
                <p class="mt-3 text-sm leading-6 text-content-muted">Gestisci clienti, incassi e fatture elettroniche in un unico spazio di lavoro.</p>
            </header>

            <form wire:submit="authenticate" class="mt-8 space-y-5" novalidate>
                <div>
                    <label for="email" class="text-sm font-medium text-content">Email</label>
                    <input
                        wire:model="email"
                        id="email"
                        type="email"
                        inputmode="email"
                        autocomplete="email"
                        autofocus
                        @class([
                            'mt-2 block h-12 w-full rounded-lg border bg-white px-4 text-sm text-content transition placeholder:text-content-muted/70 focus:border-primary focus:outline-none focus:ring-3 focus:ring-primary/15',
                            'border-border hover:border-border-strong' => ! $errors->has('email'),
                            'border-danger focus:border-danger focus:ring-danger/15' => $errors->has('email'),
                        ])
                        @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                    >
                    @error('email')
                        <p id="email-error" class="mt-2 text-sm text-danger" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="text-sm font-medium text-content">Password</label>
                    <input
                        wire:model="password"
                        id="password"
                        type="password"
                        autocomplete="current-password"
                        @class([
                            'mt-2 block h-12 w-full rounded-lg border bg-white px-4 text-sm text-content transition placeholder:text-content-muted/70 focus:border-primary focus:outline-none focus:ring-3 focus:ring-primary/15',
                            'border-border hover:border-border-strong' => ! $errors->has('password'),
                            'border-danger focus:border-danger focus:ring-danger/15' => $errors->has('password'),
                        ])
                        @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                    >
                    @error('password')
                        <p id="password-error" class="mt-2 text-sm text-danger" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <label class="flex min-h-11 cursor-pointer items-center gap-2.5 text-sm text-content-muted">
                    <input wire:model="remember" type="checkbox" class="size-4 rounded border-border text-primary accent-primary focus:ring-primary/20">
                    <span>Ricordami</span>
                </label>

                <button type="submit" wire:loading.attr="disabled" class="inline-flex h-12 w-full cursor-pointer items-center justify-center rounded-lg bg-primary px-5 text-sm font-semibold text-white transition hover:bg-primary-hover focus:outline-none focus:ring-3 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-60">
                    <span wire:loading.remove wire:target="authenticate">Accedi</span>
                    <span wire:loading wire:target="authenticate" role="status">Accesso in corso...</span>
                </button>
            </form>
        </section>

        <aside class="relative order-1 flex min-h-52 flex-col overflow-hidden bg-[linear-gradient(145deg,var(--color-sidebar-background),var(--color-indigo-950))] p-6 text-white sm:min-h-64 sm:p-10 md:order-2 lg:p-14">
            <div class="absolute -right-20 -top-20 size-64 rounded-full bg-indigo-500/10" aria-hidden="true"></div>
            <div class="absolute -bottom-24 -left-16 size-72 rounded-full border border-white/10" aria-hidden="true"></div>
            <img src="{{ asset('brand/logo-white.svg') }}" alt="Fatturino" class="relative h-9 w-auto self-start">
            <div class="relative mt-auto max-w-sm pt-12 sm:pt-16">
                <p class="text-sm font-medium text-indigo-200">Fatturazione senza attrito</p>
                <p class="mt-3 text-2xl font-medium leading-tight text-white">Un flusso chiaro, dalla fattura all'incasso.</p>
            </div>
        </aside>
    </div>
</main>
