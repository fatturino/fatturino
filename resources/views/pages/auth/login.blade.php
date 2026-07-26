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

<main class="flex min-h-dvh items-center justify-center bg-[linear-gradient(135deg,var(--color-mist),white)] p-4 lg:p-8">
    <div class="grid w-full max-w-5xl overflow-hidden border border-border-light bg-white shadow-[var(--shadow-elevated)] md:grid-cols-2">
        <section class="order-2 p-7 sm:p-10 lg:order-1 lg:p-14">
            <img src="{{ asset('brand/logo-dark.svg') }}" alt="Fatturino" class="h-9 w-auto">
            <div class="mt-10"><p class="text-xs font-bold tracking-[0.14em] text-primary">BENTORNATO</p><h1 class="mt-2 text-3xl font-bold tracking-tight text-content">Accedi al tuo account</h1><p class="mt-3 text-sm leading-6 text-content-muted">Gestisci clienti, incassi e fatture elettroniche in un unico spazio di lavoro.</p></div>
            <form wire:submit="authenticate" class="mt-8 space-y-5">
                <div><label for="email" class="text-sm font-semibold text-content">Email</label><input wire:model="email" id="email" type="email" autocomplete="email" autofocus class="mt-2 block w-full rounded-md border border-border px-4 py-3 text-sm focus:border-primary focus:ring-3 focus:ring-primary/15">@error('email') <p class="mt-2 text-sm text-danger" role="alert">{{ $message }}</p> @enderror</div>
                <div><label for="password" class="text-sm font-semibold text-content">Password</label><input wire:model="password" id="password" type="password" autocomplete="current-password" class="mt-2 block w-full rounded-md border border-border px-4 py-3 text-sm focus:border-primary focus:ring-3 focus:ring-primary/15">@error('password') <p class="mt-2 text-sm text-danger" role="alert">{{ $message }}</p> @enderror</div>
                <label class="flex cursor-pointer items-center gap-2 text-sm text-content-muted"><input wire:model="remember" type="checkbox" class="size-4 cursor-pointer rounded border-border text-primary focus:ring-primary/30"> Ricordami</label>
                <button type="submit" wire:loading.attr="disabled" class="w-full cursor-pointer rounded-md bg-primary px-5 py-3 text-sm font-bold text-white hover:bg-teal/90 disabled:cursor-not-allowed disabled:opacity-60"><span wire:loading.remove wire:target="authenticate">Accedi</span><span wire:loading wire:target="authenticate">Accesso in corso...</span></button>
            </form>
        </section>
        <aside class="order-1 flex min-h-64 flex-col justify-end bg-[linear-gradient(145deg,var(--color-ink),var(--color-indigo))] p-7 text-white sm:p-10 lg:order-2 lg:p-14"><img src="{{ asset('brand/logo-white.svg') }}" alt="Fatturino" class="h-10 w-auto self-start"><div class="mt-auto pt-16"><p class="text-xs font-bold tracking-[0.14em] text-aqua">FATTURAZIONE SENZA ATTRITO</p><p class="mt-4 max-w-sm text-2xl font-semibold leading-tight">Un flusso chiaro, dalla fattura all'incasso.</p></div></aside>
    </div>
</main>
