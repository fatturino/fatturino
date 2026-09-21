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

    public ?string $loginHint = null;

    public function mount(LoginCustomizer $customizer): void
    {
        if (User::query()->doesntExist()) {
            $this->redirectRoute('setup', navigate: false);

            return;
        }
        $credentials = $customizer->credentials() ?? [];
        $this->email = (string) ($credentials['email'] ?? '');
        $this->password = (string) ($credentials['password'] ?? '');
        $this->loginHint = $customizer->hint();
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

<main class="flex min-h-dvh items-center bg-app-background px-4 py-6 sm:px-6 sm:py-10 lg:px-8">
    <div class="mx-auto grid w-full max-w-6xl overflow-hidden rounded-xl border border-border bg-surface shadow-[var(--shadow-elevated)] lg:grid-cols-[minmax(0,0.94fr)_minmax(23rem,1.06fr)]">
        <section class="px-6 py-8 sm:px-10 sm:py-10 lg:px-14 lg:py-14">
            <img src="{{ asset('brand/logo-dark.svg') }}" alt="Fatturino" class="h-8 w-auto sm:h-9">

            <header class="mt-10 max-w-md sm:mt-12">
                <p class="text-sm font-semibold text-primary">Bentornato</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-content sm:text-[2rem]">Accedi al tuo account</h1>
                <p class="mt-3 max-w-sm text-sm leading-6 text-content-muted">Continua a gestire fatture, clienti e incassi dal tuo spazio di lavoro.</p>
            </header>

            @if ($loginHint)
                <div class="mt-6 rounded-lg border border-info/20 bg-info-bg px-4 py-3 text-sm leading-5 text-info" role="status">
                    {{ $loginHint }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mt-6 rounded-lg border border-danger/20 bg-danger-bg px-4 py-3 text-sm leading-5 text-danger" role="alert" tabindex="-1" aria-labelledby="login-error-title">
                    <p id="login-error-title" class="font-semibold">Controlla i dati di accesso</p>
                    <p class="mt-1">Correggi i campi evidenziati e riprova.</p>
                </div>
            @endif

            <form wire:submit="authenticate" class="mt-8 space-y-6" novalidate aria-busy="false" wire:loading.attr="aria-busy" wire:target="authenticate">
                <div class="space-y-2">
                    <label for="email" class="block text-sm font-semibold text-content">Email</label>
                    <input
                        wire:model="email"
                        id="email"
                        type="email"
                        inputmode="email"
                        autocomplete="email"
                        autofocus
                        @class([
                            'block h-12 w-full rounded-lg border bg-white px-4 text-sm text-content transition-[border-color,box-shadow] duration-150 placeholder:text-content-muted/70 hover:border-border-strong focus:border-primary focus:outline-none focus:ring-3 focus:ring-primary/15',
                            'border-border' => ! $errors->has('email'),
                            'border-danger focus:border-danger focus:ring-danger/15' => $errors->has('email'),
                        ])
                        @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                    >
                    @error('email')
                        <p id="email-error" class="text-sm leading-5 text-danger" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-2">
                    <label for="password" class="block text-sm font-semibold text-content">Password</label>
                    <input
                        wire:model="password"
                        id="password"
                        type="password"
                        autocomplete="current-password"
                        @class([
                            'block h-12 w-full rounded-lg border bg-white px-4 text-sm text-content transition-[border-color,box-shadow] duration-150 placeholder:text-content-muted/70 hover:border-border-strong focus:border-primary focus:outline-none focus:ring-3 focus:ring-primary/15',
                            'border-border' => ! $errors->has('password'),
                            'border-danger focus:border-danger focus:ring-danger/15' => $errors->has('password'),
                        ])
                        @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                    >
                    @error('password')
                        <p id="password-error" class="text-sm leading-5 text-danger" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <x-toggle wire:model="remember" label="Ricordami su questo dispositivo" />

                <button type="submit" wire:loading.attr="disabled" wire:target="authenticate" class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-lg bg-primary px-5 text-sm font-semibold text-white transition-[background-color,transform,box-shadow] duration-150 hover:bg-primary-hover active:translate-y-px focus:outline-none focus:ring-3 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-60">
                    <span wire:loading.remove wire:target="authenticate">Accedi</span>
                    <span wire:loading wire:target="authenticate" class="inline-flex items-center gap-2" role="status">
                        <span class="size-4 animate-spin rounded-full border-2 border-white/35 border-t-white" aria-hidden="true"></span>
                        <span>Accesso in corso</span>
                    </span>
                </button>
            </form>
        </section>

        <aside class="relative hidden overflow-hidden bg-sidebar-background p-10 text-sidebar-text lg:flex lg:flex-col lg:p-14">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_92%_10%,rgba(109,99,208,0.32),transparent_29%),radial-gradient(circle_at_16%_90%,rgba(109,99,208,0.17),transparent_36%)]" aria-hidden="true"></div>
            <div class="absolute inset-x-0 top-0 h-px bg-white/15" aria-hidden="true"></div>

            <img src="{{ asset('brand/logo-white.svg') }}" alt="Fatturino" class="relative h-9 w-auto self-start">

            <div class="relative mt-auto max-w-md">
                <p class="text-sm font-semibold text-indigo-200">Il tuo lavoro, in ordine</p>
                <h2 class="mt-3 text-3xl font-semibold tracking-tight text-white">Dalla fattura all'incasso, con ogni informazione al posto giusto.</h2>
                <p class="mt-4 max-w-sm text-sm leading-6 text-sidebar-muted">Documenti, clienti e scadenze restano collegati per rendere più semplice ogni giornata amministrativa.</p>

                <div class="mt-10 grid grid-cols-3 gap-3 border-t border-white/10 pt-6 text-sm">
                    <div>
                        <p class="font-semibold text-white">Documenti</p>
                        <p class="mt-1 text-sidebar-muted">sempre tracciati</p>
                    </div>
                    <div>
                        <p class="font-semibold text-white">Clienti</p>
                        <p class="mt-1 text-sidebar-muted">sempre collegati</p>
                    </div>
                    <div>
                        <p class="font-semibold text-white">Incassi</p>
                        <p class="mt-1 text-sidebar-muted">sempre visibili</p>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</main>
