<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>Autorizza accesso MCP - {{ config('app.name', 'Fatturino') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700,800&display=swap" rel="stylesheet">
    @vite('resources/css/fatturino.css')
</head>
<body class="bg-canvas font-sans text-content antialiased">
@php($redirectUri = request()->query('redirect_uri', $client->redirect_uris[0] ?? null))
<main class="flex min-h-dvh items-center justify-center bg-app-background px-4 py-8">
    <div class="w-full max-w-lg overflow-hidden rounded-xl border border-border bg-surface shadow-[var(--shadow-elevated)]">
        <div class="border-b border-border px-6 py-5 sm:px-8">
            <img src="{{ asset('brand/logo-dark.svg') }}" alt="Fatturino" class="h-8 w-auto">
            <h1 class="mt-6 text-2xl font-semibold tracking-tight text-content">Autorizza un agent AI</h1>
            <p class="mt-2 text-sm leading-6 text-content-muted">
                Un client esterno chiede di usare i tool MCP di questa istanza (creazione autofatture in bozza, senza invio SDI).
            </p>
        </div>

        <div class="space-y-4 px-6 py-5 sm:px-8">
            <div class="rounded-lg border border-border bg-surface-muted p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-content-muted">Chi chiede l’accesso</p>
                <p class="mt-2 text-base font-semibold text-content">{{ $client->name }}</p>
                @if ($client->client_uri ?? null)
                    <x-app-link
                        :href="$client->client_uri"
                        external
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-1 block text-sm text-primary underline break-all"
                    >
                        {{ $client->client_uri }}
                    </x-app-link>
                @endif
                @if ($redirectUri)
                    <p class="mt-3 text-xs leading-5 text-content-muted break-all">
                        Dopo l’approvazione tornerà a: <span class="font-medium text-content">{{ $redirectUri }}</span>
                    </p>
                @endif
            </div>

            <div class="rounded-lg border border-border p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-content-muted">Sei connesso come</p>
                <p class="mt-2 font-medium text-content">{{ $user->email }}</p>
            </div>

            <div class="rounded-lg border border-border p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-content-muted">Permessi richiesti</p>
                @if (count($scopes) > 0)
                    <ul class="mt-3 space-y-2">
                        @foreach ($scopes as $scope)
                            <li class="text-sm text-content">{{ $scope->description ?: $scope->id }}</li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-3 text-sm text-content">Accesso ai tool MCP di Fatturino (`mcp:use`).</p>
                @endif
            </div>
        </div>

        <div class="flex flex-col-reverse gap-3 border-t border-border px-6 py-5 sm:flex-row sm:px-8">
            <form method="POST" action="{{ route('passport.authorizations.deny') }}" class="sm:flex-1">
                @csrf
                @method('DELETE')
                <input type="hidden" name="client_id" value="{{ $client->id }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="inline-flex h-12 w-full items-center justify-center rounded-lg border border-border bg-white px-5 text-sm font-semibold text-content transition hover:bg-surface-muted focus:outline-none focus:ring-3 focus:ring-primary/15">
                    Rifiuta
                </button>
            </form>

            <form method="POST" action="{{ route('passport.authorizations.approve') }}" class="sm:flex-1" id="authorizeForm">
                @csrf
                <input type="hidden" name="client_id" value="{{ $client->id }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" id="authorizeButton" class="inline-flex h-12 w-full items-center justify-center rounded-lg bg-primary px-5 text-sm font-semibold text-white transition hover:bg-primary-hover focus:outline-none focus:ring-3 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-60">
                    <span id="authorizeText">Approva</span>
                </button>
            </form>
        </div>
    </div>
</main>
<script>
    document.getElementById('authorizeForm')?.addEventListener('submit', function () {
        const button = document.getElementById('authorizeButton');
        const label = document.getElementById('authorizeText');
        if (button) {
            button.disabled = true;
        }
        if (label) {
            label.textContent = 'Autorizzazione in corso…';
        }
    });
</script>
</body>
</html>
