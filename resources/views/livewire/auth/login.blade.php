<div class="w-full max-w-md mx-auto">
    {{-- Accent bar + heading --}}
    <div class="mb-6">
        <div class="w-10 h-1 rounded-full bg-accent mb-4"></div>
        <h1 class="text-3xl font-bold text-base-content tracking-tight">{{ __('app.auth.welcome_back') }}</h1>
        <p class="text-base text-base-content/60 mt-1">{{ __('app.auth.login_subtitle') }}</p>
    </div>

    {{-- Plugin injection point: login messages, hints, etc. --}}
    @foreach(app(\App\Services\PluginRegistry::class)->injections('login-before-form') as $__view)
        @include($__view)
    @endforeach

    {{-- Plugin-provided login hint (e.g., demo credentials) --}}
    @if($hint = app(\App\Contracts\LoginCustomizer::class)->hint())
        <x-alert variant="info" class="text-sm mb-4">
            {{ $hint }}
        </x-alert>
    @endif

    {{-- Login form --}}
    <x-form wire:submit="login">
        <x-input
            wire:model="email"
            label="{{ __('app.auth.email') }}"
            type="email"
            icon="o-envelope"
            autofocus
            autocomplete="email"
        />

        <x-input
            wire:model="password"
            label="{{ __('app.auth.password') }}"
            type="password"
            icon="o-lock-closed"
            autocomplete="current-password"
        />

        <x-checkbox
            wire:model="remember"
            label="{{ __('app.auth.remember_me') }}"
        />

        <x-slot:actions>
            <x-button
                label="{{ __('app.auth.login') }}"
                type="submit"
                icon="o-arrow-right-end-on-rectangle"
                variant="primary" class="w-full"
                spinner="login"
            />
        </x-slot:actions>
    </x-form>
</div>
