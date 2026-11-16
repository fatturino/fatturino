<?php

use App\Traits\Toast;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component {
    use Toast;

    public function revoke(string $tokenId): void
    {
        $token = Auth::user()?->tokens()->whereKey($tokenId)->first();

        if ($token === null) {
            $this->error('Connessione non trovata.');

            return;
        }

        $token->revoke();
        $token->refreshToken?->revoke();

        $this->success('Accesso MCP revocato.');
    }

    public function render()
    {
        $tokens = Auth::user()
            ?->tokens()
            ->with('client')
            ->where('revoked', false)
            ->latest('created_at')
            ->get() ?? collect();

        return view('pages::settings.mcp', [
            'tokens' => $tokens,
            'mcpUrl' => url('/mcp'),
        ]);
    }
};
?>
<x-slot:header>
    <div>
        <p class="text-xs font-bold uppercase tracking-[.12em] text-content-muted">Integrazioni</p>
        <h1 class="text-lg font-bold text-content">Connessioni MCP</h1>
    </div>
</x-slot:header>

<section class="space-y-6">
    <x-settings.panel title="Come collegare un agent" subtitle="L’agent apre il browser su questa istanza. Approvi tu l’accesso: non si incollano token a mano.">
        <p class="text-sm leading-6 text-content-muted">
            Configura il client MCP con l’URL qui sotto. Al primo uso ti chiederà di autorizzare e ti mostrerà chi sta chiedendo l’accesso.
        </p>
        <p class="mt-3 font-mono text-sm text-content break-all">{{ $mcpUrl }}</p>
    </x-settings.panel>

    <x-settings.panel flush>
        <div class="border-b border-border-light p-5">
            <h2 class="text-base font-bold text-content">Accessi autorizzati</h2>
            <p class="mt-1 text-sm text-content-muted">Revoca subito un agent se non lo riconosci più.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-border bg-surface-muted text-content-muted">
                    <tr>
                        <th class="px-4 py-3 text-xs font-medium">Client</th>
                        <th class="px-4 py-3 text-xs font-medium">Scope</th>
                        <th class="px-4 py-3 text-xs font-medium">Creato</th>
                        <th class="px-4 py-3 text-xs font-medium">Scade</th>
                        <th class="px-4 py-3 text-xs font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($tokens as $token)
                        <tr wire:key="mcp-token-{{ $token->id }}">
                            <td class="px-4 py-3">
                                <p class="font-semibold text-content">{{ $token->client?->name ?? $token->name ?? 'Client MCP' }}</p>
                                <p class="font-mono text-xs text-content-muted">{{ $token->id }}</p>
                            </td>
                            <td class="px-4 py-3 text-content-muted">
                                {{ collect($token->scopes ?? [])->implode(', ') ?: '—' }}
                            </td>
                            <td class="px-4 py-3 text-content-muted">
                                {{ optional($token->created_at)->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-4 py-3 text-content-muted">
                                {{ optional($token->expires_at)->format('d/m/Y H:i') ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <x-button label="Revoca" wire:click="revoke('{{ $token->id }}')" wire:confirm="Revocare questo accesso MCP?" variant="secondary" size="sm" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-content-muted">
                                Nessuna connessione MCP attiva.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-settings.panel>
</section>
